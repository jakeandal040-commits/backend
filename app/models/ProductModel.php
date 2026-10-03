<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class ProductModel extends Model {
    protected $table = 'products';
    protected $primary_key = 'id';
    protected $fillable = ['product_name', 'description', 'price', 'quantity'];
    protected $guarded = ['id', 'created_at'];
    protected $timestamps = false;
    protected $has_soft_delete = false;

    public function all_products()
    {
        return $this->_order_by('id', 'DESC');
    }
    public function find_product($id)
    {
        return $this->_find($id);
    }
    public function create_product(array $data)
    {
        $id = $this->_insert($data);
        return $this->_find($id);
    }
    public function update_product($id, array $data)
    {
        $this->_update($id, $data);
        return $this->_find($id);
    }
    public function delete_product($id)
    {
        return $this->_delete($id);
    }
}
