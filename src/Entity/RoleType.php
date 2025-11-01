<?php

namespace App\Entity;

enum RoleType: string
{
    case FRONTEND = 'frontend';
    case BACKEND = 'backend';
    case FULLSTACK = 'fullstack';
    case DEVOPS = 'devops';
}
