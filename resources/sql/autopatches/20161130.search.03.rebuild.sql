USE {$NAMESPACE}_search;
CREATE FULLTEXT INDEX  IF NOT EXISTS `key_corpus` ON `search_documentfield`(`corpus`,`stemmedCorpus`) WITH PARSER NGRAM;
