<?php
class Create_products_table
{
    public function up()
    {
        lava_instance()->db->raw('CREATE TABLE IF NOT EXISTS products (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            product_name VARCHAR(100) NOT NULL,
            description TEXT NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            quantity INT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT products_price_nonnegative CHECK (price >= 0),
            CONSTRAINT products_quantity_nonnegative CHECK (quantity >= 0)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }
    public function down() { lava_instance()->db->raw('DROP TABLE IF EXISTS products'); }
}
