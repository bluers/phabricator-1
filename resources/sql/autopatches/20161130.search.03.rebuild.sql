USE {$NAMESPACE}_search;
select if (
    exists(
        select distinct index_name from information_schema.statistics
        where table_schema = '{$NAMESPACE}_search'
        and table_name = 'search_documentfield' and index_name like 'key_corpus'
    )
    ,'select ''index key_corpus exists'' _______;'
    ,'CREATE FULLTEXT INDEX `key_corpus` ON `search_documentfield`(`corpus`,`stemmedCorpus`) WITH PARSER NGRAM;') into @a;
PREPARE stmt1 FROM @a;
EXECUTE stmt1;
DEALLOCATE PREPARE stmt1;
