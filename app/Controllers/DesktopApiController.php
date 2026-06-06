<?php

namespace Controllers;

require_once __DIR__ . '/../Core/Controller.php';
require_once __DIR__ . '/../Helpers/ApiHelper.php';
require_once __DIR__ . '/../Models/CustomerWebLoginAttemptModel.php';
require_once __DIR__ . '/../Services/JwtService.php';
require_once __DIR__ . '/../../utils/database.php';

use ApiHelper;
use App\Services\JwtService;
use Core\Controller;
use Models\CustomerWebLoginAttemptModel;

class DesktopApiController extends Controller
{
    private const JWT_TTL_SECONDS = 1296000; // 15 dias
    private const LOGIN_ATTEMPT_WINDOW_SECONDS = 600; // 10 minutos
    private const LOGIN_ATTEMPT_LIMIT = 5;

    private \Database $db;
    private CustomerWebLoginAttemptModel $loginAttemptModel;

    public function __construct()
    {
        parent::__construct();
        $this->db = new \Database();
        $this->loginAttemptModel = new CustomerWebLoginAttemptModel();
    }

    public function login(): void
    {
        ApiHelper::respondIfOptions();
        ApiHelper::allowedMethodsPost();

        $payload = ApiHelper::getJsonBody();
        $codeAccess = $this->requiredString($payload, 'codeAccess');
        $login = trim((string) ($payload['login'] ?? $payload['username'] ?? $payload['email'] ?? $payload['administrator'] ?? ''));
        if ($login === '') {
            ApiHelper::respond(['error' => 'El campo login es obligatorio'], 422);
        }
        $password = $this->requiredString($payload, 'password');
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $lockout = $this->loginAttemptModel->getLockoutStatus(
            $login,
            $codeAccess,
            self::LOGIN_ATTEMPT_WINDOW_SECONDS,
            self::LOGIN_ATTEMPT_LIMIT
        );

        if ($lockout['isLocked']) {
            ApiHelper::respond([
                'error' => 'Demasiados intentos fallidos. Intenta nuevamente cuando termine la ventana de 10 minutos.',
                'code' => 'too_many_attempts',
                'retryAfter' => $lockout['retryAfter'],
                'lockedUntil' => $lockout['lockedUntil'],
            ], 429);
        }

        $row = $this->db->fetchOne(
            "SELECT c.Id AS CustomerId, c.Name AS CustomerName, c.IsActive, a.Id AS AdminId, a.Username, a.Email, a.Password, a.Role
             FROM Customers c
             JOIN AdministratorsDesktop a ON a.CustomerApiId = c.Id
             WHERE c.CodeAccess = ?
               AND c.IsActive = 1
               AND COALESCE(a.Removed, 0) = 0
               AND a.Role = 2
               AND (a.Username = ? OR a.Email = ?)
             LIMIT 1",
            [$codeAccess, $login, $login]
        );

        if (!$row || !$this->passwordMatches($password, (string) ($row['Password'] ?? ''))) {
            $this->loginAttemptModel->record([
                'loginIdentifier' => $login,
                'codeAccess' => $codeAccess,
                'customerId' => $row['CustomerId'] ?? null,
                'adminId' => $row['AdminId'] ?? null,
                'ipAddress' => $ipAddress,
                'userAgent' => $userAgent,
                'wasSuccessful' => false,
                'failureReason' => $row ? 'invalid_password' : 'invalid_login',
            ]);

            ApiHelper::respond(['error' => 'Credenciales invalidas'], 401);
        }

        $this->loginAttemptModel->record([
            'loginIdentifier' => $login,
            'codeAccess' => $codeAccess,
            'customerId' => $row['CustomerId'] ?? null,
            'adminId' => $row['AdminId'] ?? null,
            'ipAddress' => $ipAddress,
            'userAgent' => $userAgent,
            'wasSuccessful' => true,
            'failureReason' => null,
        ]);

        $jwt = new JwtService();
        $token = $jwt->createToken([
            'name' => $row['CustomerName'],
            'customerId' => $row['CustomerId'],
        ], self::JWT_TTL_SECONDS);

        ApiHelper::respond([
            'status' => 'success',
            'token' => $token,
            'expiresIn' => self::JWT_TTL_SECONDS,
            'expiresAt' => date('Y-m-d H:i:s', time() + self::JWT_TTL_SECONDS),
            'customer' => [
                'name' => $row['CustomerName'],
                'customerId' => $row['CustomerId'],
            ],
        ]);
    }

    public function dashboard(): void
    {
        ApiHelper::respondIfOptions();
        ApiHelper::allowedMethodsGet();

        $customerId = $this->customerId();
        [$from, $to] = $this->dateRange();
        $expiringDays = $this->intQuery('expiringDays', 3, 1, 365);
        $lowStock = $this->intQuery('lowStock', 5, 0, 100000);

        ApiHelper::respond([
            'customerApiId' => $customerId,
            'range' => $this->rangePayload($from, $to),
            'members' => [
                'total' => $this->scalar("SELECT COUNT(*) FROM UsersDesktop WHERE CustomerApiId = ? AND COALESCE(Removed, 0) = 0", [$customerId]),
                'newInRange' => $this->scalar(
                    "SELECT COUNT(*) FROM UsersDesktop WHERE CustomerApiId = ? AND COALESCE(Removed, 0) = 0 AND CreatedOn BETWEEN ? AND ?",
                    [$customerId, $from, $to]
                ),
                'dailyNew' => $this->dailyUsers($customerId, $from, $to),
            ],
            'memberships' => [
                'activeTotal' => $this->activeMemberships($customerId),
                'expiringTotal' => $this->expiringMemberships($customerId, $expiringDays),
                'soldTotal' => $this->saleCategoryCount($customerId, $from, $to, 'membership'),
                'income' => $this->saleCategoryIncome($customerId, $from, $to, 'membership'),
                'dailySales' => $this->dailyCategorySales($customerId, $from, $to, 'membership'),
            ],
            'products' => [
                'lowStockTotal' => $this->lowStockCount($customerId, $lowStock),
                'soldTotal' => $this->saleCategoryCount($customerId, $from, $to, 'product'),
                'income' => $this->saleCategoryIncome($customerId, $from, $to, 'product'),
                'dailySales' => $this->dailyCategorySales($customerId, $from, $to, 'product'),
            ],
            'attendances' => $this->attendanceSummary($customerId, $from, $to),
            'sales' => $this->salesSummary($customerId, $from, $to),
        ]);
    }

    public function users(): void
    {
        ApiHelper::respondIfOptions();
        ApiHelper::allowedMethodsGet();

        $customerId = $this->customerId();
        [$from, $to] = $this->dateRange();
        [$page, $perPage, $offset] = $this->pagination();
        $search = trim((string) ($_GET['search'] ?? ''));
        [$sortColumn, $sortDir] = $this->usersSort();

        $where = 'CustomerApiId = ? AND COALESCE(Removed, 0) = 0';
        $params = [$customerId];

        if ($search !== '') {
            $where .= ' AND (Fullname LIKE ? OR Code LIKE ? OR PhoneNumber LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like);
        }

        $total = $this->scalar("SELECT COUNT(*) FROM UsersDesktop WHERE {$where}", $params);
        $members = $this->db->fetchAll(
            "SELECT Id, Fullname, PhoneNumber, PhoneNumberEmergency, Gender, BirthDate, Code, CreatedOn
             FROM UsersDesktop
             WHERE {$where}
             ORDER BY {$sortColumn} {$sortDir}, Fullname ASC
             LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, $offset])
        );

        ApiHelper::respond([
            'summary' => [
                'totalCurrent' => $this->scalar("SELECT COUNT(*) FROM UsersDesktop WHERE CustomerApiId = ? AND COALESCE(Removed, 0) = 0", [$customerId]),
                'newInRange' => $this->scalar(
                    "SELECT COUNT(*) FROM UsersDesktop WHERE CustomerApiId = ? AND COALESCE(Removed, 0) = 0 AND CreatedOn BETWEEN ? AND ?",
                    [$customerId, $from, $to]
                ),
                'dailyNew' => $this->dailyUsers($customerId, $from, $to),
                'sortBy' => $_GET['sortBy'] ?? 'fullname',
                'sortDir' => strtolower((string) ($_GET['sortDir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc',
            ],
            'members' => $this->pagePayload($members, $total, $page, $perPage),
        ]);
    }

    public function memberships(): void
    {
        ApiHelper::respondIfOptions();
        ApiHelper::allowedMethodsGet();

        $customerId = $this->customerId();
        $expiringDays = $this->intQuery('expiringDays', 3, 1, 365);
        [$page, $perPage, $offset] = $this->pagination();
        $status = strtolower(trim((string) ($_GET['status'] ?? 'all')));
        [$membershipOrderBy, $sortBy, $sortDir] = $this->membershipSort();

        $where = 'CustomerApiId = ?';
        $params = [$customerId];
        if ($status === 'active') {
            $where .= ' AND Expiration > 0';
        } elseif ($status === 'expired') {
            $where .= ' AND Expiration <= 0';
        } elseif ($status === 'expiring') {
            $where .= ' AND Expiration BETWEEN 1 AND ?';
            $params[] = $expiringDays;
        }

        $total = $this->scalar("SELECT COUNT(*) FROM ViewSubscriptions WHERE {$where}", $params);
        $rows = $this->db->fetchAll(
            "SELECT Id,
                    UserId,
                    Fullname,
                    PhoneNumber,
                    SubscriptionId,
                    StartDate,
                    EndingDate,
                    Expiration,
                    CASE
                        WHEN Id IS NULL OR Expiration IS NULL THEN 'none'
                        WHEN Expiration <= 0 THEN 'expired'
                        WHEN Expiration BETWEEN 1 AND ? THEN 'expiring'
                        ELSE 'active'
                    END AS Status,
                    CASE WHEN Expiration BETWEEN 1 AND ? THEN 1 ELSE 0 END AS IsExpiring,
                    Warning,
                    Finished,
                    Registered
             FROM ViewSubscriptions
             WHERE {$where}
             ORDER BY {$membershipOrderBy}
             LIMIT ? OFFSET ?",
            array_merge([$expiringDays, $expiringDays], $params, [$perPage, $offset])
        );

        foreach ($rows as &$row) {
            $row['status'] = $row['Status'] ?? null;
            $row['isExpiring'] = (bool) ($row['IsExpiring'] ?? false);
        }
        unset($row);

        ApiHelper::respond([
            'summary' => [
                'activeToday' => $this->activeMemberships($customerId),
                'activeTotal' => $this->activeMemberships($customerId),
                'expiringTotal' => $this->expiringMemberships($customerId, $expiringDays),
                'expiringDays' => $expiringDays,
                'sortBy' => $sortBy,
                'sortDir' => $sortDir,
            ],
            'memberships' => $this->pagePayload($rows, $total, $page, $perPage),
        ]);
    }

    public function products(): void
    {
        ApiHelper::respondIfOptions();
        ApiHelper::allowedMethodsGet();

        $customerId = $this->customerId();
        [$from, $to] = $this->dateRange();
        $lowStock = $this->intQuery('lowStock', 5, 0, 100000);
        $lowStockOnly = $this->boolQuery('lowStockOnly');
        [$page, $perPage, $offset] = $this->pagination();
        $search = trim((string) ($this->queryValue('search', '') ?? ''));

        $where = 'CustomerApiId = ? AND COALESCE(IsDeleted, 0) = 0';
        $params = [$customerId];
        if ($search !== '') {
            $where .= ' AND (Name LIKE ? OR Code LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like);
        }
        if ($lowStockOnly) {
            $where .= ' AND Stock <= ?';
            $params[] = $lowStock;
        }

        $total = $this->scalar("SELECT COUNT(*) FROM ViewProductStock WHERE {$where}", $params);
        $products = $this->db->fetchAll(
            "SELECT ProductId, Code, Name, Active, CurrentPrice, Stock
             FROM ViewProductStock
             WHERE {$where}
             ORDER BY Name ASC
             LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, $offset])
        );

        [$historyPage, $historyPerPage, $historyOffset] = $this->pagination('historyPage', 'historyPerPage');
        $historySearchWhere = '';
        $historySearchParams = [];
        if ($search !== '') {
            $historySearchWhere = ' AND (p.Name LIKE ? OR p.Code LIKE ? OR p.Description LIKE ? OR ps.Notes LIKE ? OR ps.MovementType LIKE ?)';
            $like = '%' . $search . '%';
            $historySearchParams = [$like, $like, $like, $like, $like];
        }
        $historyTotal = $this->scalar(
            "SELECT COUNT(*)
             FROM ProductStockDesktop ps
             LEFT JOIN ProductDesktop p ON p.Id = ps.ProductId
             WHERE ps.CustomerApiId = ? AND COALESCE(ps.IsDeleted, 0) = 0 AND ps.MovementDate BETWEEN ? AND ?
             {$historySearchWhere}",
            array_merge([$customerId, $from, $to], $historySearchParams)
        );
        $history = $this->db->fetchAll(
            "SELECT ps.Id, ps.ProductId, p.Name AS ProductName, ps.MovementType, ps.Quantity, ps.MovementDate, ps.Notes, ps.CreatedBy
             FROM ProductStockDesktop ps
             LEFT JOIN ProductDesktop p ON p.Id = ps.ProductId
             WHERE ps.CustomerApiId = ? AND COALESCE(ps.IsDeleted, 0) = 0 AND ps.MovementDate BETWEEN ? AND ?
             {$historySearchWhere}
             ORDER BY ps.MovementDate DESC
             LIMIT ? OFFSET ?",
            array_merge([$customerId, $from, $to], $historySearchParams, [$historyPerPage, $historyOffset])
        );

        ApiHelper::respond([
            'range' => $this->rangePayload($from, $to),
            'filters' => [
                'search' => $search,
                'lowStock' => $lowStock,
                'lowStockOnly' => $lowStockOnly,
            ],
            'summary' => [
                'lowStockTotal' => $this->lowStockCount($customerId, $lowStock),
                'lowStockThreshold' => $lowStock,
            ],
            'products' => $this->pagePayload($products, $total, $page, $perPage),
            'stockHistory' => $this->pagePayload($history, $historyTotal, $historyPage, $historyPerPage),
        ]);
    }

    public function attendances(): void
    {
        ApiHelper::respondIfOptions();
        ApiHelper::allowedMethodsGet();

        $customerId = $this->customerId();
        [$from, $to] = $this->dateRange();
        [$page, $perPage, $offset] = $this->pagination();
        [$memberPage, $memberPerPage, $memberOffset] = $this->pagination('memberPage', 'memberPerPage');
        $reentriesOnly = $this->boolQuery('reentriesOnly');
        $memberHaving = $reentriesOnly ? 'HAVING COUNT(*) >= 2' : '';
        $memberSearch = trim((string) ($_GET['search'] ?? $_GET['memberSearch'] ?? ''));
        $memberSearchWhere = '';
        $memberSearchParams = [];
        if ($memberSearch !== '') {
            $memberSearchWhere = ' AND (u.Fullname LIKE ? OR u.Code LIKE ? OR u.PhoneNumber LIKE ?)';
            $like = '%' . $memberSearch . '%';
            $memberSearchParams = [$like, $like, $like];
        }

        $total = $this->scalar(
            "SELECT COUNT(*) FROM AttendancesDesktop WHERE CustomerApiId = ? AND COALESCE(Removed, 0) = 0 AND STR_TO_DATE(CheckIn, '%Y-%m-%d %H:%i:%s') BETWEEN ? AND ?",
            [$customerId, $from, $to]
        );
        $rows = $this->db->fetchAll(
            "SELECT a.Id, a.CheckIn, a.Active, a.UserId, u.Fullname, u.Code
             FROM AttendancesDesktop a
             LEFT JOIN UsersDesktop u ON u.Id = a.UserId
             WHERE a.CustomerApiId = ? AND COALESCE(a.Removed, 0) = 0 AND STR_TO_DATE(a.CheckIn, '%Y-%m-%d %H:%i:%s') BETWEEN ? AND ?
             ORDER BY STR_TO_DATE(a.CheckIn, '%Y-%m-%d %H:%i:%s') DESC
             LIMIT ? OFFSET ?",
            [$customerId, $from, $to, $perPage, $offset]
        );
        $memberTotal = $this->scalar(
            "SELECT COUNT(*)
             FROM (
                SELECT a.UserId, DATE(STR_TO_DATE(a.CheckIn, '%Y-%m-%d %H:%i:%s')) AS AttendanceDate
                FROM AttendancesDesktop a
                LEFT JOIN UsersDesktop u ON u.Id = a.UserId
                WHERE a.CustomerApiId = ? AND COALESCE(a.Removed, 0) = 0 AND STR_TO_DATE(a.CheckIn, '%Y-%m-%d %H:%i:%s') BETWEEN ? AND ?
                {$memberSearchWhere}
                GROUP BY a.UserId, DATE(STR_TO_DATE(a.CheckIn, '%Y-%m-%d %H:%i:%s'))
                {$memberHaving}
             ) groupedMembers",
            array_merge([$customerId, $from, $to], $memberSearchParams)
        );
        $memberRows = $this->db->fetchAll(
            "SELECT a.UserId,
                    DATE(STR_TO_DATE(a.CheckIn, '%Y-%m-%d %H:%i:%s')) AS date,
                    COALESCE(u.Fullname, '') AS Fullname,
                    u.Code,
                    COUNT(*) AS totalAttempts,
                    SUM(CASE WHEN a.Active = 1 THEN 1 ELSE 0 END) AS allowedAttempts,
                    SUM(CASE WHEN a.Active = 0 THEN 1 ELSE 0 END) AS deniedAttempts,
                    MAX(STR_TO_DATE(a.CheckIn, '%Y-%m-%d %H:%i:%s')) AS lastAttemptAt
             FROM AttendancesDesktop a
             LEFT JOIN UsersDesktop u ON u.Id = a.UserId
             WHERE a.CustomerApiId = ? AND COALESCE(a.Removed, 0) = 0 AND STR_TO_DATE(a.CheckIn, '%Y-%m-%d %H:%i:%s') BETWEEN ? AND ?
             {$memberSearchWhere}
             GROUP BY a.UserId, DATE(STR_TO_DATE(a.CheckIn, '%Y-%m-%d %H:%i:%s')), u.Fullname, u.Code
             {$memberHaving}
             ORDER BY lastAttemptAt DESC, Fullname ASC
             LIMIT ? OFFSET ?",
            array_merge([$customerId, $from, $to], $memberSearchParams, [$memberPerPage, $memberOffset])
        );

        foreach ($memberRows as &$memberRow) {
            $totalAttempts = (int) ($memberRow['totalAttempts'] ?? 0);
            $memberRow['hasReentries'] = $totalAttempts >= 2;
            $memberRow['reentriesCount'] = max(0, $totalAttempts - 1);
            $attempts = $this->db->fetchAll(
                "SELECT a.Id, a.CheckIn, a.Active, a.UserId
                 FROM AttendancesDesktop a
                 WHERE a.CustomerApiId = ? AND COALESCE(a.Removed, 0) = 0 AND a.UserId = ?
                   AND DATE(STR_TO_DATE(a.CheckIn, '%Y-%m-%d %H:%i:%s')) = ?
                 ORDER BY STR_TO_DATE(a.CheckIn, '%Y-%m-%d %H:%i:%s') DESC",
                [$customerId, $memberRow['UserId'], $memberRow['date']]
            );
            $memberRow['attempts'] = $attempts;
        }
        unset($memberRow);

        ApiHelper::respond([
            'range' => $this->rangePayload($from, $to),
            'filters' => [
                'reentriesOnly' => $reentriesOnly,
                'search' => $memberSearch,
            ],
            'summary' => $this->attendanceSummary($customerId, $from, $to),
            'attendances' => $this->pagePayload($rows, $total, $page, $perPage),
            'memberAccess' => $this->pagePayload($memberRows, $memberTotal, $memberPage, $memberPerPage),
        ]);
    }

    public function sales(): void
    {
        ApiHelper::respondIfOptions();
        ApiHelper::allowedMethodsGet();

        $customerId = $this->customerId();
        [$from, $to] = $this->dateRange();
        [$page, $perPage, $offset] = $this->pagination();
        $cashRegisterRangeWhere = $this->cashRegisterRangeWhere();
        $cashRegisterRangeParams = [$from, $to, $from, $to, $from, $to];

        $cashTotal = $this->scalar(
            "SELECT COUNT(*) FROM CashRegisterDesktop WHERE CustomerApiId = ? AND COALESCE(IsDeleted, 0) = 0 AND {$cashRegisterRangeWhere}",
            array_merge([$customerId], $cashRegisterRangeParams)
        );
        $cashRegisters = $this->db->fetchAll(
            "SELECT cr.*,
                    (
                        SELECT a.Username
                        FROM AdministratorsDesktop a
                        WHERE a.CustomerApiId = cr.CustomerApiId
                          AND COALESCE(a.Removed, 0) = 0
                          AND (a.Id = cr.OpenedBy OR a.Username = cr.OpenedBy)
                        ORDER BY a.Username ASC
                        LIMIT 1
                    ) AS OpenedByName,
                    (
                        SELECT a.Username
                        FROM AdministratorsDesktop a
                        WHERE a.CustomerApiId = cr.CustomerApiId
                          AND COALESCE(a.Removed, 0) = 0
                          AND (a.Id = cr.ClosedBy OR a.Username = cr.ClosedBy)
                        ORDER BY a.Username ASC
                        LIMIT 1
                    ) AS ClosedByName
             FROM CashRegisterDesktop cr
             WHERE cr.CustomerApiId = ? AND COALESCE(cr.IsDeleted, 0) = 0 AND {$cashRegisterRangeWhere}
             ORDER BY cr.OpenedAt DESC
             LIMIT ? OFFSET ?",
            array_merge([$customerId], $cashRegisterRangeParams, [$perPage, $offset])
        );

        foreach ($cashRegisters as &$cashRegister) {
            $tickets = $this->db->fetchAll(
                "SELECT * FROM SaleTicketDesktop
                 WHERE CustomerApiId = ? AND CashRegisterId = ? AND COALESCE(IsDeleted, 0) = 0 AND SaleDate BETWEEN ? AND ?
                 ORDER BY SaleDate DESC",
                [$customerId, $cashRegister['Id'], $from, $to]
            );

            foreach ($tickets as &$ticket) {
                $ticket['items'] = $this->db->fetchAll(
                    "SELECT * FROM SaleTicketItemDesktop
                     WHERE CustomerApiId = ? AND SaleTicketId = ? AND COALESCE(IsDeleted, 0) = 0
                     ORDER BY CreatedOn ASC",
                    [$customerId, $ticket['Id']]
                );
            }

            $cashRegister['tickets'] = $tickets;
        }
        unset($cashRegister, $ticket);

        ApiHelper::respond([
            'range' => $this->rangePayload($from, $to),
            'summary' => $this->salesSummary($customerId, $from, $to),
            'cashRegisters' => $this->pagePayload($cashRegisters, $cashTotal, $page, $perPage),
        ]);
    }

    public function admins(): void
    {
        ApiHelper::respondIfOptions();
        ApiHelper::allowedMethodsGet();

        $customerId = $this->customerId();
        [$from, $to] = $this->dateRange();
        [$page, $perPage, $offset] = $this->pagination();
        $search = trim((string) ($_GET['search'] ?? ''));

        $where = 'CustomerApiId = ? AND COALESCE(Removed, 0) = 0';
        $params = [$customerId];
        if ($search !== '') {
            $where .= ' AND (Username LIKE ? OR Email LIKE ? OR PhoneNumber LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like);
        }

        $total = $this->scalar("SELECT COUNT(*) FROM AdministratorsDesktop WHERE {$where}", $params);
        $admins = $this->db->fetchAll(
            "SELECT Id, Username, Email, PhoneNumber, Manager, EmailConfirmed, EmailConfirmedOn, Role
             FROM AdministratorsDesktop
             WHERE {$where}
             ORDER BY Username ASC
             LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, $offset])
        );

        [$historyPage, $historyPerPage, $historyOffset] = $this->pagination('historyPage', 'historyPerPage');
        $historySearch = trim((string) ($_GET['historySearch'] ?? ''));
        $historyWhere = "h.CustomerApiId = ? AND COALESCE(h.Removed, 0) = 0 AND STR_TO_DATE(h.DatetimeOperation, '%Y-%m-%d %H:%i:%s') BETWEEN ? AND ?";
        $historyParams = [$customerId, $from, $to];
        if ($historySearch !== '') {
            $historyWhere .= ' AND (h.Operation LIKE ? OR a.Username LIKE ? OR a.Email LIKE ?)';
            $like = '%' . $historySearch . '%';
            array_push($historyParams, $like, $like, $like);
        }

        $historyTotal = $this->scalar(
            "SELECT COUNT(*)
             FROM HistoryOperationsDesktop h
             LEFT JOIN AdministratorsDesktop a ON a.Id = h.AdminId
             WHERE {$historyWhere}",
            $historyParams
        );
        $history = $this->db->fetchAll(
            "SELECT h.Id, h.Operation, h.DatetimeOperation, h.AdminId, a.Username, a.Email
             FROM HistoryOperationsDesktop h
             LEFT JOIN AdministratorsDesktop a ON a.Id = h.AdminId
             WHERE {$historyWhere}
             ORDER BY STR_TO_DATE(h.DatetimeOperation, '%Y-%m-%d %H:%i:%s') DESC
             LIMIT ? OFFSET ?",
            array_merge($historyParams, [$historyPerPage, $historyOffset])
        );

        ApiHelper::respond([
            'range' => $this->rangePayload($from, $to),
            'admins' => $this->pagePayload($admins, $total, $page, $perPage),
            'history' => $this->pagePayload($history, $historyTotal, $historyPage, $historyPerPage),
        ]);
    }

    public function chartsOverview(): void
    {
        ApiHelper::respondIfOptions();
        ApiHelper::allowedMethodsGet();

        $customerId = $this->customerId();
        [$from, $to] = $this->dateRange();
        $expiringDays = $this->intQuery('expiringDays', 3, 1, 365);

        ApiHelper::respond([
            'customerApiId' => $customerId,
            'range' => $this->rangePayload($from, $to),
            'users' => $this->usersChartPayload($customerId, $from, $to),
            'memberships' => $this->categoryChartPayload($customerId, $from, $to, 'membership', $expiringDays),
            'products' => $this->categoryChartPayload($customerId, $from, $to, 'product'),
            'attendances' => $this->attendancesChartPayload($customerId, $from, $to),
            'sales' => $this->salesChartPayload($customerId, $from, $to),
        ]);
    }

    public function chartsUsers(): void
    {
        ApiHelper::respondIfOptions();
        ApiHelper::allowedMethodsGet();

        $customerId = $this->customerId();
        [$from, $to] = $this->dateRange();

        ApiHelper::respond([
            'customerApiId' => $customerId,
            'range' => $this->rangePayload($from, $to),
            'users' => $this->usersChartPayload($customerId, $from, $to),
        ]);
    }

    public function chartsMemberships(): void
    {
        ApiHelper::respondIfOptions();
        ApiHelper::allowedMethodsGet();

        $customerId = $this->customerId();
        [$from, $to] = $this->dateRange();
        $expiringDays = $this->intQuery('expiringDays', 3, 1, 365);

        ApiHelper::respond([
            'customerApiId' => $customerId,
            'range' => $this->rangePayload($from, $to),
            'memberships' => $this->categoryChartPayload($customerId, $from, $to, 'membership', $expiringDays),
        ]);
    }

    public function chartsProducts(): void
    {
        ApiHelper::respondIfOptions();
        ApiHelper::allowedMethodsGet();

        $customerId = $this->customerId();
        [$from, $to] = $this->dateRange();

        ApiHelper::respond([
            'customerApiId' => $customerId,
            'range' => $this->rangePayload($from, $to),
            'products' => $this->categoryChartPayload($customerId, $from, $to, 'product'),
        ]);
    }

    public function chartsAttendances(): void
    {
        ApiHelper::respondIfOptions();
        ApiHelper::allowedMethodsGet();

        $customerId = $this->customerId();
        [$from, $to] = $this->dateRange();

        ApiHelper::respond([
            'customerApiId' => $customerId,
            'range' => $this->rangePayload($from, $to),
            'attendances' => $this->attendancesChartPayload($customerId, $from, $to),
        ]);
    }

    public function chartsSales(): void
    {
        ApiHelper::respondIfOptions();
        ApiHelper::allowedMethodsGet();

        $customerId = $this->customerId();
        [$from, $to] = $this->dateRange();

        ApiHelper::respond([
            'customerApiId' => $customerId,
            'range' => $this->rangePayload($from, $to),
            'sales' => $this->salesChartPayload($customerId, $from, $to),
        ]);
    }

    private function requiredString(array $payload, string $key): string
    {
        $value = isset($payload[$key]) ? trim((string) $payload[$key]) : '';
        if ($value === '') {
            ApiHelper::respond(['error' => "El campo {$key} es obligatorio"], 422);
        }

        return $value;
    }

    private function passwordMatches(string $plain, string $stored): bool
    {
        if ($stored === '') {
            return false;
        }

        $info = password_get_info($stored);
        if (($info['algo'] ?? 0) !== 0 && password_verify($plain, $stored)) {
            return true;
        }

        if (hash_equals($stored, $plain)) {
            return true;
        }

        $sqlVarcharHash = $this->hashSha256SqlVarcharString($plain);
        if ($sqlVarcharHash !== null && hash_equals($stored, $sqlVarcharHash)) {
            return true;
        }

        $sqlVarcharHashHex = $this->hashSha256SqlVarcharHex($plain);
        return hash_equals(strtolower($stored), $sqlVarcharHashHex);
    }

    private function hashSha256SqlVarcharString(string $password, string $codePage = 'Windows-1252'): ?string
    {
        $passwordBytes = $this->convertEncoding($password, 'UTF-8', $codePage);
        if ($passwordBytes === null) {
            return null;
        }

        $hashBytes = hash('sha256', $passwordBytes, true);

        return $this->convertEncoding($hashBytes, $codePage, 'UTF-8');
    }

    private function hashSha256SqlVarcharHex(string $password, string $codePage = 'Windows-1252'): string
    {
        $passwordBytes = $this->convertEncoding($password, 'UTF-8', $codePage) ?? $password;

        return hash('sha256', $passwordBytes);
    }

    private function convertEncoding(string $value, string $from, string $to): ?string
    {
        if (function_exists('mb_convert_encoding')) {
            $converted = @mb_convert_encoding($value, $to, $from);
            if ($converted !== false) {
                return $converted;
            }
        }

        if (function_exists('iconv')) {
            $converted = @iconv($from, $to . '//TRANSLIT', $value);
            if ($converted !== false) {
                return $converted;
            }
        }

        return null;
    }

    private function customerId(): string
    {
        $customerId = $GLOBALS['desktop_jwt_customer_id'] ?? '';
        if ($customerId === '') {
            ApiHelper::respond(['error' => 'Token sin customerId'], 401);
        }

        return (string) $customerId;
    }

    private function dateRange(): array
    {
        $range = strtolower(trim((string) ($_GET['range'] ?? 'today')));
        $todayStart = strtotime(date('Y-m-d 00:00:00'));
        $todayEnd = strtotime(date('Y-m-d 23:59:59'));

        if ($range === 'custom') {
            $from = isset($_GET['from']) ? strtotime((string) $_GET['from']) : false;
            $to = isset($_GET['to']) ? strtotime((string) $_GET['to']) : false;
            if ($from === false || $to === false) {
                ApiHelper::respond(['error' => 'from y to son obligatorios para range=custom'], 422);
            }
            if ($from > $to) {
                ApiHelper::respond(['error' => 'from no puede ser mayor que to'], 422);
            }

            return [date('Y-m-d 00:00:00', $from), date('Y-m-d 23:59:59', $to)];
        }

        if ($range === 'week') {
            $days = 7;
        } elseif (in_array($range, ['fifteen', '15', '15days'], true)) {
            $days = 15;
        } elseif (in_array($range, ['month', '30', '30days'], true)) {
            $days = 30;
        } else {
            $days = 1;
        }

        $from = $days === 1 ? $todayStart : strtotime('-' . ($days - 1) . ' days', $todayStart);

        return [date('Y-m-d H:i:s', $from), date('Y-m-d H:i:s', $todayEnd)];
    }

    private function rangePayload(string $from, string $to): array
    {
        return [
            'from' => $from,
            'to' => $to,
        ];
    }

    private function pagination(string $pageKey = 'page', string $perPageKey = 'perPage'): array
    {
        $page = $this->intQuery($pageKey, 1, 1, 1000000);
        $perPage = $this->intQuery($perPageKey, 25, 1, 100);

        return [$page, $perPage, ($page - 1) * $perPage];
    }

    private function membershipSort(): array
    {
        $sortBy = strtolower(trim((string) ($_GET['sortBy'] ?? 'expiration'), " \t\n\r\0\x0B+"));
        $sortDirSql = strtolower(trim((string) ($_GET['sortDir'] ?? 'asc'))) === 'desc' ? 'DESC' : 'ASC';
        $sortDirLabel = $sortDirSql === 'DESC' ? 'desc' : 'asc';

        if (in_array($sortBy, ['expiration', 'expiation'], true)) {
            return [
                "CASE WHEN Expiration IS NULL THEN 1 ELSE 0 END ASC, CAST(Expiration AS SIGNED) {$sortDirSql}, Fullname ASC",
                'expiration',
                $sortDirLabel,
            ];
        }

        if ($sortBy === 'endingdate') {
            return [
                "CASE WHEN EndingDate IS NULL THEN 1 ELSE 0 END ASC, STR_TO_DATE(EndingDate, '%Y-%m-%d') {$sortDirSql}, Fullname ASC",
                'endingDate',
                $sortDirLabel,
            ];
        }

        return [
            "Fullname {$sortDirSql}",
            'fullname',
            $sortDirLabel,
        ];
    }

    private function usersSort(): array
    {
        $sortBy = strtolower(trim((string) ($_GET['sortBy'] ?? 'fullname')));
        $sortDir = strtolower(trim((string) ($_GET['sortDir'] ?? 'asc'))) === 'desc' ? 'DESC' : 'ASC';

        $columns = [
            'fullname' => 'Fullname',
            'name' => 'Fullname',
            'createdon' => 'CreatedOn',
            'created' => 'CreatedOn',
        ];

        return [$columns[$sortBy] ?? 'Fullname', $sortDir];
    }

    private function cashRegisterRangeWhere(): string
    {
        return '(
            OpenedAt BETWEEN ? AND ?
            OR ClosedAt BETWEEN ? AND ?
            OR (OpenedAt <= ? AND (ClosedAt IS NULL OR ClosedAt >= ?))
        )';
    }

    private function intQuery(string $key, int $default, int $min, int $max): int
    {
        $value = $this->queryValue($key, $default);
        $value = (int) $value;

        return max($min, min($max, $value));
    }

    private function boolQuery(string $key, bool $default = false): bool
    {
        $value = $this->queryValue($key, null);
        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        $value = strtolower(trim((string) $value));

        return in_array($value, ['1', 'true', 'yes', 'on', 'si'], true);
    }

    private function queryValue(string $key, $default = null)
    {
        if (array_key_exists($key, $_GET)) {
            return $_GET[$key];
        }

        $filters = $this->queryFilters();

        return array_key_exists($key, $filters) ? $filters[$key] : $default;
    }

    private function queryFilters(): array
    {
        static $filters = null;

        if ($filters !== null) {
            return $filters;
        }

        $filters = [];
        if (!isset($_GET['filters'])) {
            return $filters;
        }

        $rawFilters = $_GET['filters'];
        if (is_array($rawFilters)) {
            $filters = $rawFilters;
            return $filters;
        }

        $decoded = json_decode((string) $rawFilters, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $filters = $decoded;
        }

        return $filters;
    }

    private function scalar(string $sql, array $params = []): float
    {
        $row = $this->db->fetchOne($sql, $params);
        if (!$row) {
            return 0;
        }

        return (float) array_values($row)[0];
    }

    private function pagePayload(array $data, float $total, int $page, int $perPage): array
    {
        return [
            'data' => $data,
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => (int) $total,
                'totalPages' => (int) ceil($total / $perPage),
            ],
        ];
    }

    private function activeMemberships(string $customerId): float
    {
        return $this->scalar('SELECT COUNT(*) FROM ViewSubscriptions WHERE CustomerApiId = ? AND Expiration > 0', [$customerId]);
    }

    private function expiringMemberships(string $customerId, int $days): float
    {
        return $this->scalar('SELECT COUNT(*) FROM ViewSubscriptions WHERE CustomerApiId = ? AND Expiration BETWEEN 1 AND ?', [$customerId, $days]);
    }

    private function lowStockCount(string $customerId, int $threshold): float
    {
        return $this->scalar(
            'SELECT COUNT(*) FROM ViewProductStock WHERE CustomerApiId = ? AND COALESCE(IsDeleted, 0) = 0 AND Stock <= ?',
            [$customerId, $threshold]
        );
    }

    private function dailyUsers(string $customerId, string $from, string $to): array
    {
        $rows = $this->db->fetchAll(
            "SELECT DATE(CreatedOn) AS date, COUNT(*) AS total
             FROM UsersDesktop
             WHERE CustomerApiId = ? AND COALESCE(Removed, 0) = 0 AND CreatedOn BETWEEN ? AND ?
             GROUP BY DATE(CreatedOn)
             ORDER BY date ASC",
            [$customerId, $from, $to]
        );

        return $this->completeDailySeries($from, $to, $rows, ['total']);
    }

    private function categoryCondition(string $category): string
    {
        return $category === 'membership' ? 'i.SubscriptionId IS NOT NULL' : 'i.ProductId IS NOT NULL';
    }

    private function saleCategoryCount(string $customerId, string $from, string $to, string $category): float
    {
        return $this->scalar(
            "SELECT COALESCE(SUM(i.Quantity), 0)
             FROM SaleTicketItemDesktop i
             JOIN SaleTicketDesktop t ON t.Id = i.SaleTicketId
             WHERE t.CustomerApiId = ? AND t.Active = 1 AND COALESCE(t.IsDeleted, 0) = 0 AND COALESCE(i.IsDeleted, 0) = 0
               AND t.SaleDate BETWEEN ? AND ? AND " . $this->categoryCondition($category),
            [$customerId, $from, $to]
        );
    }

    private function saleCategoryIncome(string $customerId, string $from, string $to, string $category): float
    {
        return $this->scalar(
            "SELECT COALESCE(SUM(i.LineTotal), 0)
             FROM SaleTicketItemDesktop i
             JOIN SaleTicketDesktop t ON t.Id = i.SaleTicketId
             WHERE t.CustomerApiId = ? AND t.Active = 1 AND COALESCE(t.IsDeleted, 0) = 0 AND COALESCE(i.IsDeleted, 0) = 0
               AND t.SaleDate BETWEEN ? AND ? AND " . $this->categoryCondition($category),
            [$customerId, $from, $to]
        );
    }

    private function saleCategoryIncomeByPayment(string $customerId, string $from, string $to, string $category): array
    {
        $rows = $this->db->fetchAll(
            "SELECT t.PaymentMethod,
                    COUNT(DISTINCT t.Id) AS tickets,
                    COALESCE(SUM(i.Quantity), 0) AS quantity,
                    COALESCE(SUM(i.LineTotal), 0) AS income
             FROM SaleTicketItemDesktop i
             JOIN SaleTicketDesktop t ON t.Id = i.SaleTicketId
             WHERE t.CustomerApiId = ? AND t.Active = 1 AND COALESCE(t.IsDeleted, 0) = 0 AND COALESCE(i.IsDeleted, 0) = 0
               AND t.SaleDate BETWEEN ? AND ? AND " . $this->categoryCondition($category) . "
             GROUP BY t.PaymentMethod
             ORDER BY income DESC",
            [$customerId, $from, $to]
        );

        return $this->paymentIncomePayload($rows);
    }

    private function saleIncomeByPayment(string $customerId, string $from, string $to): array
    {
        $rows = $this->db->fetchAll(
            "SELECT PaymentMethod, COUNT(*) AS tickets, COALESCE(SUM(TotalAmount), 0) AS income
             FROM SaleTicketDesktop
             WHERE CustomerApiId = ? AND COALESCE(IsDeleted, 0) = 0 AND SaleDate BETWEEN ? AND ? AND Active = 1
             GROUP BY PaymentMethod
             ORDER BY income DESC",
            [$customerId, $from, $to]
        );

        return $this->paymentIncomePayload($rows);
    }

    private function paymentIncomePayload(array $rows): array
    {
        $byPayment = [
            'card' => 0.0,
            'cash' => 0.0,
            'transfer' => 0.0,
        ];
        $breakdown = [];

        foreach ($rows as $row) {
            $rawMethod = trim((string) ($row['PaymentMethod'] ?? ''));
            $key = $this->paymentMethodKey($rawMethod);
            $income = (float) ($row['income'] ?? 0);

            if (!array_key_exists($key, $byPayment)) {
                $byPayment[$key] = 0.0;
            }

            $byPayment[$key] += $income;
            $item = [
                'paymentMethod' => $rawMethod,
                'key' => $key,
                'tickets' => (int) ($row['tickets'] ?? 0),
                'income' => $income,
            ];

            if (array_key_exists('quantity', $row)) {
                $item['quantity'] = (float) ($row['quantity'] ?? 0);
            }

            $breakdown[] = $item;
        }

        return [
            'byPayment' => $byPayment,
            'breakdown' => $breakdown,
        ];
    }

    private function paymentMethodKey(string $paymentMethod): string
    {
        $normalized = strtolower(trim($paymentMethod));

        if (in_array($normalized, ['card', 'tarjeta', 'credit_card', 'debit_card', 'credito', 'debito'], true)) {
            return 'card';
        }

        if (in_array($normalized, ['cash', 'efectivo'], true)) {
            return 'cash';
        }

        if (in_array($normalized, ['transfer', 'transferencia', 'bank_transfer', 'spei'], true)) {
            return 'transfer';
        }

        return preg_replace('/[^a-z0-9]+/', '_', $normalized) ?: 'unknown';
    }

    private function dailyCategorySales(string $customerId, string $from, string $to, string $category): array
    {
        $rows = $this->db->fetchAll(
            "SELECT DATE(t.SaleDate) AS date, COALESCE(SUM(i.Quantity), 0) AS quantity, COALESCE(SUM(i.LineTotal), 0) AS income
             FROM SaleTicketItemDesktop i
             JOIN SaleTicketDesktop t ON t.Id = i.SaleTicketId
             WHERE t.CustomerApiId = ? AND t.Active = 1 AND COALESCE(t.IsDeleted, 0) = 0 AND COALESCE(i.IsDeleted, 0) = 0
               AND t.SaleDate BETWEEN ? AND ? AND " . $this->categoryCondition($category) . "
             GROUP BY DATE(t.SaleDate)
             ORDER BY date ASC",
            [$customerId, $from, $to]
        );

        return $this->completeDailySeries($from, $to, $rows, ['quantity', 'income']);
    }

    private function productSalesRanking(string $customerId, string $from, string $to): array
    {
        $rows = $this->db->fetchAll(
            "SELECT p.Id AS productId,
                    COALESCE(p.Code, '') AS productCode,
                    p.Name AS productName,
                    COALESCE(s.tickets, 0) AS tickets,
                    COALESCE(s.quantity, 0) AS quantity,
                    COALESCE(s.income, 0) AS income
             FROM ProductDesktop p
             LEFT JOIN (
                SELECT i.ProductId,
                       COUNT(DISTINCT t.Id) AS tickets,
                       COALESCE(SUM(i.Quantity), 0) AS quantity,
                       COALESCE(SUM(i.LineTotal), 0) AS income
                FROM SaleTicketItemDesktop i
                JOIN SaleTicketDesktop t ON t.Id = i.SaleTicketId
                WHERE t.CustomerApiId = ? AND t.Active = 1 AND COALESCE(t.IsDeleted, 0) = 0 AND COALESCE(i.IsDeleted, 0) = 0
                  AND t.SaleDate BETWEEN ? AND ? AND i.ProductId IS NOT NULL
                GROUP BY i.ProductId
             ) s ON s.ProductId = p.Id
             WHERE p.CustomerApiId = ? AND COALESCE(p.IsDeleted, 0) = 0",
            [$customerId, $from, $to, $customerId]
        );

        $products = array_map(static function (array $row): array {
            return [
                'productId' => $row['productId'],
                'productCode' => $row['productCode'] ?? '',
                'productName' => $row['productName'] ?? '',
                'tickets' => (int) ($row['tickets'] ?? 0),
                'quantity' => (float) ($row['quantity'] ?? 0),
                'income' => (float) ($row['income'] ?? 0),
            ];
        }, $rows);

        $byQuantity = $products;
        usort($byQuantity, static function (array $a, array $b): int {
            $quantityCompare = $b['quantity'] <=> $a['quantity'];
            if ($quantityCompare !== 0) {
                return $quantityCompare;
            }

            return $b['income'] <=> $a['income'];
        });

        $byIncome = $products;
        usort($byIncome, static function (array $a, array $b): int {
            $incomeCompare = $b['income'] <=> $a['income'];
            if ($incomeCompare !== 0) {
                return $incomeCompare;
            }

            return $b['quantity'] <=> $a['quantity'];
        });

        return [
            'byQuantity' => $byQuantity,
            'byIncome' => $byIncome,
        ];
    }

    private function usersChartPayload(string $customerId, string $from, string $to): array
    {
        return [
            'dailyNewUsers' => $this->dailyUsers($customerId, $from, $to),
            'totalNewUsers' => $this->scalar(
                "SELECT COUNT(*) FROM UsersDesktop WHERE CustomerApiId = ? AND COALESCE(Removed, 0) = 0 AND CreatedOn BETWEEN ? AND ?",
                [$customerId, $from, $to]
            ),
        ];
    }

    private function categoryChartPayload(string $customerId, string $from, string $to, string $category, ?int $expiringDays = null): array
    {
        $payload = [
            'daily' => $this->dailyCategorySales($customerId, $from, $to, $category),
            'totalQuantity' => $this->saleCategoryCount($customerId, $from, $to, $category),
            'totalIncome' => $this->saleCategoryIncome($customerId, $from, $to, $category),
        ];

        if ($category === 'membership') {
            $days = $expiringDays ?? 3;
            $payload['expiringDays'] = $days;
            $payload['expiringTotal'] = $this->expiringMemberships($customerId, $days);
            $payload['activeTotal'] = $this->activeMemberships($customerId);
        } elseif ($category === 'product') {
            $payload['productSales'] = $this->productSalesRanking($customerId, $from, $to);
        }

        return $payload;
    }

    private function attendancesChartPayload(string $customerId, string $from, string $to): array
    {
        $latestCondition = $this->latestAttendanceCondition('a', 'ax');
        $dailyRows = $this->db->fetchAll(
            "SELECT DATE(STR_TO_DATE(a.CheckIn, '%Y-%m-%d %H:%i:%s')) AS date,
                    COUNT(*) AS total,
                    SUM(CASE WHEN a.Active = 1 THEN 1 ELSE 0 END) AS allowed,
                    SUM(CASE WHEN a.Active = 0 THEN 1 ELSE 0 END) AS denied
             FROM AttendancesDesktop a
             WHERE a.CustomerApiId = ? AND COALESCE(a.Removed, 0) = 0
               AND STR_TO_DATE(a.CheckIn, '%Y-%m-%d %H:%i:%s') BETWEEN ? AND ?
               AND {$latestCondition}
             GROUP BY DATE(STR_TO_DATE(a.CheckIn, '%Y-%m-%d %H:%i:%s'))
             ORDER BY date ASC",
            [$customerId, $from, $to]
        );

        return [
            'countMode' => 'latest_attempt_per_user_per_day',
            'daily' => $this->completeDailySeries($from, $to, $dailyRows, ['total', 'allowed', 'denied']),
            'byWeekday' => $this->completeWeekdaySeries($this->db->fetchAll(
                "SELECT DAYOFWEEK(STR_TO_DATE(a.CheckIn, '%Y-%m-%d %H:%i:%s')) AS weekday,
                        COUNT(*) AS total
                 FROM AttendancesDesktop a
                 WHERE a.CustomerApiId = ? AND COALESCE(a.Removed, 0) = 0 AND a.Active = 1
                   AND STR_TO_DATE(a.CheckIn, '%Y-%m-%d %H:%i:%s') BETWEEN ? AND ?
                   AND {$latestCondition}
                 GROUP BY DAYOFWEEK(STR_TO_DATE(a.CheckIn, '%Y-%m-%d %H:%i:%s'))
                 ORDER BY weekday ASC",
                [$customerId, $from, $to]
            )),
            'byHour' => $this->completeHourSeries($this->db->fetchAll(
                "SELECT HOUR(STR_TO_DATE(a.CheckIn, '%Y-%m-%d %H:%i:%s')) AS hour,
                        COUNT(*) AS total
                 FROM AttendancesDesktop a
                 WHERE a.CustomerApiId = ? AND COALESCE(a.Removed, 0) = 0 AND a.Active = 1
                   AND STR_TO_DATE(a.CheckIn, '%Y-%m-%d %H:%i:%s') BETWEEN ? AND ?
                   AND {$latestCondition}
                 GROUP BY HOUR(STR_TO_DATE(a.CheckIn, '%Y-%m-%d %H:%i:%s'))
                 ORDER BY hour ASC",
                [$customerId, $from, $to]
            )),
        ];
    }

    private function salesChartPayload(string $customerId, string $from, string $to): array
    {
        $dailySalesRows = $this->db->fetchAll(
            "SELECT DATE(SaleDate) AS date,
                    SUM(CASE WHEN Active = 1 THEN 1 ELSE 0 END) AS tickets,
                    SUM(CASE WHEN Active = 0 THEN 1 ELSE 0 END) AS cancelledTickets,
                    COALESCE(SUM(CASE WHEN Active = 1 THEN TotalAmount ELSE 0 END), 0) AS income
             FROM SaleTicketDesktop
             WHERE CustomerApiId = ? AND COALESCE(IsDeleted, 0) = 0 AND SaleDate BETWEEN ? AND ?
             GROUP BY DATE(SaleDate)
             ORDER BY date ASC",
            [$customerId, $from, $to]
        );

        $dailyCategoryRows = $this->db->fetchAll(
            "SELECT DATE(t.SaleDate) AS date,
                    COALESCE(SUM(CASE WHEN i.SubscriptionId IS NOT NULL THEN i.Quantity ELSE 0 END), 0) AS memberships,
                    COALESCE(SUM(CASE WHEN i.SubscriptionId IS NOT NULL THEN i.LineTotal ELSE 0 END), 0) AS membershipIncome,
                    COALESCE(SUM(CASE WHEN i.ProductId IS NOT NULL THEN i.Quantity ELSE 0 END), 0) AS products,
                    COALESCE(SUM(CASE WHEN i.ProductId IS NOT NULL THEN i.LineTotal ELSE 0 END), 0) AS productIncome
             FROM SaleTicketItemDesktop i
             JOIN SaleTicketDesktop t ON t.Id = i.SaleTicketId
             WHERE t.CustomerApiId = ? AND t.Active = 1 AND COALESCE(t.IsDeleted, 0) = 0 AND COALESCE(i.IsDeleted, 0) = 0
               AND t.SaleDate BETWEEN ? AND ?
             GROUP BY DATE(t.SaleDate)
             ORDER BY date ASC",
            [$customerId, $from, $to]
        );

        return [
            'dailyTickets' => $this->completeDailySeries($from, $to, $dailySalesRows, ['tickets', 'cancelledTickets', 'income']),
            'dailyCategories' => $this->completeDailySeries($from, $to, $dailyCategoryRows, ['memberships', 'membershipIncome', 'products', 'productIncome']),
        ];
    }

    private function completeDailySeries(string $from, string $to, array $rows, array $fields): array
    {
        $series = [];
        $start = strtotime(date('Y-m-d', strtotime($from)));
        $end = strtotime(date('Y-m-d', strtotime($to)));

        for ($day = $start; $day <= $end; $day = strtotime('+1 day', $day)) {
            $date = date('Y-m-d', $day);
            $series[$date] = ['date' => $date];
            foreach ($fields as $field) {
                $series[$date][$field] = 0;
            }
        }

        foreach ($rows as $row) {
            $date = isset($row['date']) ? date('Y-m-d', strtotime((string) $row['date'])) : null;
            if ($date === null || !isset($series[$date])) {
                continue;
            }

            foreach ($fields as $field) {
                $series[$date][$field] = (float) ($row[$field] ?? 0);
            }
        }

        return array_values($series);
    }

    private function completeWeekdaySeries(array $rows): array
    {
        $labels = [
            1 => 'Domingo',
            2 => 'Lunes',
            3 => 'Martes',
            4 => 'Miercoles',
            5 => 'Jueves',
            6 => 'Viernes',
            7 => 'Sabado',
        ];
        $series = [];

        foreach ($labels as $weekday => $label) {
            $series[$weekday] = [
                'weekday' => $weekday,
                'label' => $label,
                'total' => 0,
            ];
        }

        foreach ($rows as $row) {
            $weekday = (int) ($row['weekday'] ?? 0);
            if (isset($series[$weekday])) {
                $series[$weekday]['total'] = (float) ($row['total'] ?? 0);
            }
        }

        return array_values($series);
    }

    private function completeHourSeries(array $rows): array
    {
        $series = [];

        for ($hour = 0; $hour <= 23; $hour++) {
            $series[$hour] = [
                'hour' => $hour,
                'label' => str_pad((string) $hour, 2, '0', STR_PAD_LEFT) . ':00',
                'total' => 0,
            ];
        }

        foreach ($rows as $row) {
            $hour = (int) ($row['hour'] ?? -1);
            if (isset($series[$hour])) {
                $series[$hour]['total'] = (float) ($row['total'] ?? 0);
            }
        }

        return array_values($series);
    }

    private function latestAttendanceCondition(string $alias, string $otherAlias): string
    {
        return "NOT EXISTS (
            SELECT 1
            FROM AttendancesDesktop {$otherAlias}
            WHERE {$otherAlias}.CustomerApiId = {$alias}.CustomerApiId
                AND {$otherAlias}.UserId = {$alias}.UserId
                AND COALESCE({$otherAlias}.Removed, 0) = 0
                AND DATE(STR_TO_DATE({$otherAlias}.CheckIn, '%Y-%m-%d %H:%i:%s')) = DATE(STR_TO_DATE({$alias}.CheckIn, '%Y-%m-%d %H:%i:%s'))
                AND (
                    STR_TO_DATE({$otherAlias}.CheckIn, '%Y-%m-%d %H:%i:%s') > STR_TO_DATE({$alias}.CheckIn, '%Y-%m-%d %H:%i:%s')
                    OR (
                        STR_TO_DATE({$otherAlias}.CheckIn, '%Y-%m-%d %H:%i:%s') = STR_TO_DATE({$alias}.CheckIn, '%Y-%m-%d %H:%i:%s')
                        AND {$otherAlias}.Id > {$alias}.Id
                    )
                )
        )";
    }

    private function reentryUsersCount(string $customerId, string $from, string $to): float
    {
        return $this->scalar(
            "SELECT COUNT(*)
             FROM (
                SELECT UserId
                FROM AttendancesDesktop
                WHERE CustomerApiId = ?
                  AND COALESCE(Removed, 0) = 0
                  AND STR_TO_DATE(CheckIn, '%Y-%m-%d %H:%i:%s') BETWEEN ? AND ?
                GROUP BY UserId
                HAVING COUNT(*) >= 2
             ) reentries",
            [$customerId, $from, $to]
        );
    }

    private function totalReentriesCount(string $customerId, string $from, string $to): float
    {
        return $this->scalar(
            "SELECT COALESCE(SUM(totalAttempts - 1), 0)
             FROM (
                SELECT COUNT(*) AS totalAttempts
                FROM AttendancesDesktop
                WHERE CustomerApiId = ?
                  AND COALESCE(Removed, 0) = 0
                  AND STR_TO_DATE(CheckIn, '%Y-%m-%d %H:%i:%s') BETWEEN ? AND ?
                GROUP BY UserId
                HAVING COUNT(*) >= 2
             ) reentries",
            [$customerId, $from, $to]
        );
    }

    private function attendanceSummary(string $customerId, string $from, string $to): array
    {
        $base = "a.CustomerApiId = ? AND COALESCE(a.Removed, 0) = 0 AND STR_TO_DATE(a.CheckIn, '%Y-%m-%d %H:%i:%s') BETWEEN ? AND ?";
        $params = [$customerId, $from, $to];
        $latestCondition = $this->latestAttendanceCondition('a', 'ax');
        $total = $this->scalar("SELECT COUNT(*) FROM AttendancesDesktop a WHERE {$base} AND {$latestCondition}", $params);
        $charts = $this->attendancesChartPayload($customerId, $from, $to);

        return [
            'total' => $total,
            'allowed' => $this->scalar("SELECT COUNT(*) FROM AttendancesDesktop a WHERE {$base} AND {$latestCondition} AND a.Active = 1", $params),
            'denied' => $this->scalar("SELECT COUNT(*) FROM AttendancesDesktop a WHERE {$base} AND {$latestCondition} AND a.Active = 0", $params),
            'rawAttempts' => $this->scalar("SELECT COUNT(*) FROM AttendancesDesktop a WHERE {$base}", $params),
            'reentryUsers' => $this->reentryUsersCount($customerId, $from, $to),
            'totalReentries' => $this->totalReentriesCount($customerId, $from, $to),
            'averagePerDay' => $this->scalar(
                "SELECT COALESCE(COUNT(*) / NULLIF(COUNT(DISTINCT DATE(STR_TO_DATE(a.CheckIn, '%Y-%m-%d %H:%i:%s'))), 0), 0)
                 FROM AttendancesDesktop a WHERE {$base} AND {$latestCondition}",
                $params
            ),
            'daily' => $charts['daily'],
            'byWeekday' => $charts['byWeekday'],
            'byHour' => $charts['byHour'],
        ];
    }

    private function salesSummary(string $customerId, string $from, string $to): array
    {
        $ticketWhere = 'CustomerApiId = ? AND COALESCE(IsDeleted, 0) = 0 AND SaleDate BETWEEN ? AND ?';
        $params = [$customerId, $from, $to];
        $paymentIncome = $this->saleIncomeByPayment($customerId, $from, $to);
        $membershipPaymentIncome = $this->saleCategoryIncomeByPayment($customerId, $from, $to, 'membership');

        return [
            'tickets' => $this->scalar("SELECT COUNT(*) FROM SaleTicketDesktop WHERE {$ticketWhere} AND Active = 1", $params),
            'cancelledTickets' => $this->scalar("SELECT COUNT(*) FROM SaleTicketDesktop WHERE {$ticketWhere} AND Active = 0", $params),
            'income' => $this->scalar("SELECT COALESCE(SUM(TotalAmount), 0) FROM SaleTicketDesktop WHERE {$ticketWhere} AND Active = 1", $params),
            'membershipTickets' => $this->scalar(
                "SELECT COUNT(DISTINCT t.Id) FROM SaleTicketDesktop t JOIN SaleTicketItemDesktop i ON i.SaleTicketId = t.Id
                 WHERE t.CustomerApiId = ? AND t.Active = 1 AND COALESCE(t.IsDeleted, 0) = 0 AND COALESCE(i.IsDeleted, 0) = 0
                   AND t.SaleDate BETWEEN ? AND ? AND i.SubscriptionId IS NOT NULL",
                $params
            ),
            'productTickets' => $this->scalar(
                "SELECT COUNT(DISTINCT t.Id) FROM SaleTicketDesktop t JOIN SaleTicketItemDesktop i ON i.SaleTicketId = t.Id
                 WHERE t.CustomerApiId = ? AND t.Active = 1 AND COALESCE(t.IsDeleted, 0) = 0 AND COALESCE(i.IsDeleted, 0) = 0
                   AND t.SaleDate BETWEEN ? AND ? AND i.ProductId IS NOT NULL",
                $params
            ),
            'membershipIncome' => $this->saleCategoryIncome($customerId, $from, $to, 'membership'),
            'productIncome' => $this->saleCategoryIncome($customerId, $from, $to, 'product'),
            'byPayment' => $paymentIncome['byPayment'],
            'paymentBreakdown' => $paymentIncome['breakdown'],
            'membershipByPayment' => $membershipPaymentIncome['byPayment'],
            'membershipPaymentBreakdown' => $membershipPaymentIncome['breakdown'],
            'paymentDistribution' => $this->db->fetchAll(
                "SELECT PaymentMethod, COUNT(*) AS tickets, COALESCE(SUM(TotalAmount), 0) AS income
                 FROM SaleTicketDesktop
                 WHERE {$ticketWhere} AND Active = 1
                 GROUP BY PaymentMethod
                 ORDER BY income DESC",
                $params
            ),
        ];
    }
}
