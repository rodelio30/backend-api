<?php
namespace App\Models;
use CodeIgniter\Model;

class PaymentModel extends Model
{
    // protected $table = 'payments_table';
    protected $table = 'payment_table';
    protected $primaryKey = 'id';
    // for now we will keep these fields allowed client_id only
    protected $allowedFields = [
        'client_id', 'addon_id', 'paypal_order_id', 'transaction_id', 'payer_email',
        'amount', 'currency', 'status', 'created_at'
    ];

    // this was used soon if we have products list table
    // protected $allowedFields = [
    //     'product_id', 'paypal_order_id', 'payer_email',
    //     'amount', 'currency', 'status', 'created_at'
    // ];
}
