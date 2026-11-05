<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table         = 'usr_account';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
protected $allowedFields = ['username', 'email_workspace', 'password', 'user_role_id', 'account_status', 'session_id', 'session_last_seen'];
    protected $useTimestamps = true;
}