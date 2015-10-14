
CREATE TABLE IF NOT EXISTS cs_test_table (
	test_id serial NOT NULL UNIQUE PRIMARY KEY,
	description text,
	the_number integer NOT NULL DEFAULT 0,
	is_active boolean NOT NULL DEFAULT TRUE,
	date_created timestamp DEFAULT NOW()
);
TRUNCATE cs_test_table;

INSERT INTO cs_test_table (description, the_number, is_active, date_created) VALUES ('first', 1, true, ADDDATE(NOW(), interval 2 minute));
INSERT INTO cs_test_table (description, the_number, is_active, date_created) VALUES ('second', 2, true, NOW());
INSERT INTO cs_test_table (description, the_number, is_active, date_created) VALUES ('third', 3, true, NOW());
INSERT INTO cs_test_table (description, the_number, is_active, date_created) VALUES ('fourth', 4 , false, NOW());
INSERT INTO cs_test_table (description, the_number, is_active, date_created) VALUES ('another', 999, true, ADDDATE(NOW(), interval 3 month));