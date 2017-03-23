USE {$NAMESPACE}_repository;
select if (
    exists(
        SELECT *
FROM information_schema.tables
WHERE table_schema = '{$NAMESPACE}_repository'
AND table_name = 'repository_downloads'
    )
    ,'select ''repository_downloads exists'' _______;'
    ,'CREATE TABLE {$NAMESPACE}_repository.repository_downloads (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, `key` VARCHAR(256) NOT NULL COLLATE {$COLLATE_TEXT}, count INT UNSIGNED NOT NULL, UNIQUE KEY(`key`)) ENGINE=InnoDB, COLLATE {$COLLATE_TEXT};') into @a;
PREPARE stmt1 FROM @a;
EXECUTE stmt1;
DEALLOCATE PREPARE stmt1;

