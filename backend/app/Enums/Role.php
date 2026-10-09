<?php

namespace App\Enums;

enum Role: string
{
    case Customer = 'customer';
    case Organizer = 'organizer';
    case Admin = 'admin';
}
