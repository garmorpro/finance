ALTER TABLE categories
    ADD COLUMN group_id BIGINT UNSIGNED NULL AFTER parent_category_id,
    ADD CONSTRAINT fk_categories_group FOREIGN KEY (group_id) REFERENCES category_groups (id) ON DELETE SET NULL;
