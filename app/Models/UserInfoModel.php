<?php

namespace App\Models;

use CodeIgniter\Model;

class UserInfoModel extends Model
{
    protected $table         = 'usr_account_info';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['account_id', 'first_name', 'middle_name', 'last_name', 'sex', 'email', 'phone_number', 'avatar'];
    protected $useTimestamps = true;
}
