-- sea actulaiza la tabla  SentMessagesDesktop agragando em DateSent tipo datetime
ALTER TABLE SentMessagesDesktop ADD COLUMN DateSent DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER SentDay;

