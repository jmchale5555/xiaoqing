<?php

namespace Model;

class User
{

    public const ROLE_CUSTOMER = 'customer';
    public const ROLE_STAFF = 'staff';
    public const ROLE_MANAGER = 'manager';

    use Model;

    protected $table = 'users';

    protected $allowedColumns = [
        'name',
        'email',
        'password',
        'role',
    ];

    public function validate($data)
    {
        $this->errors = [];

        if (empty($data['name']))
        {
            $this->errors['name'] = "Name is required";
        }
        else
        if (empty($data['email']))
        {
            $this->errors['email'] = "Email is required";
        }
        else
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL))
        {
            $this->errors['email'] = "Enter a valid email address";
        }

        if (empty($data['password']))
        {
            $this->errors['password'] = "Password is required";
        }

        if ($data['confirm'])
        {
            if ($data['confirm'] !== $data['password'])
            {
                $this->errors['confirm'] = "Passwords do not match";
            }
            else
                unset($data[2]);
        }

        if (empty($this->errors))
        {
            return true;
        }

        return false;
    }
}
