USE {$NAMESPACE}_user;
ALTER TABLE `user` ADD COLUMN `requestAsDev` tinyint(1) NOT NULL DEFAULT 0;
