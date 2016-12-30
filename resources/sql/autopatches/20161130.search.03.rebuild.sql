USE {$NAMESPACE}_search;
CREATE FULLTEXT INDEX `key_corpus` ON `search_documentfield`(`corpus`,`stemmedCorpus`) WITH PARSER NGRAM;
