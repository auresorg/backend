<?php

namespace App\Entity;

enum RoleType: string
{
    case FRONTEND = 'frontend';
    case BACKEND = 'backend';
    case FULLSTACK = 'fullstack';
    case DEVOPS = 'devops';
    case MOBILE = 'mobile';
    case AIML = 'aiml';
    case PRODUCT = 'product';
    case QA = 'qa';
    case DESIGNER = 'designer';
    case BLOCKCHAIN = 'blockchain';
}
