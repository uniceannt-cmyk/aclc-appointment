ALTER TABLE appointments
    ADD COLUMN education_level VARCHAR(20) DEFAULT NULL AFTER service,
    ADD COLUMN strand VARCHAR(50) DEFAULT NULL AFTER education_level,
    ADD COLUMN grade_level VARCHAR(2) DEFAULT NULL AFTER strand,
    ADD COLUMN program VARCHAR(100) DEFAULT NULL AFTER grade_level,
    ADD COLUMN college_year_level VARCHAR(3) DEFAULT NULL AFTER program,
    ADD COLUMN section VARCHAR(50) DEFAULT NULL AFTER college_year_level;
