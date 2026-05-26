<?php
declare(strict_types=1);
 
// prevents html injection by escaping special chars, handles utf-8, xss
function e(string $val): string {
    return htmlspecialchars($val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}