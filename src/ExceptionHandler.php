<?php

namespace codesaur\Http\Application;

use codesaur\Http\Message\ReasonPrhase;

class ExceptionHandler implements ExceptionHandlerInterface
{
    public function exception(\Throwable $throwable)
    {
        $code = $throwable->getCode();
        $message = $throwable->getMessage();
        $title = \get_class($throwable);
        
        if ($code != 0) {
            $status = "STATUS_$code";
            $reasonPhrase = ReasonPrhase::class;
            if (\defined("$reasonPhrase::$status")
                    && !\headers_sent()
            ) {
                \http_response_code($code);
            }
            $title .= " $code";
        }
        
        \error_log("$title: $message");
        
        $host = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off')
            || $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
        $host .= $_SERVER['HTTP_HOST'] ?? 'localhost';        
        echo '<!doctype html>'
            . '<html lang="en">'
            . "<head><meta charset=\"utf-8\"><title>$title</title></head>"
            . "<body><h1>$title</h1><p>$message</p><hr><a href=\"$host\">$host</a></body>"
            . '</html>'; 

        if (\defined('CODESAUR_DEVELOPMENT')
                && CODESAUR_DEVELOPMENT
        ) {
            echo '<hr>';
            \var_dump($throwable->getTrace());
        }
    }
}
