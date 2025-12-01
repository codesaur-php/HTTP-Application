<?php

namespace codesaur\Http\Application;

use codesaur\Http\Message\ReasonPrhase;

/**
 * Class ExceptionHandler
 *
 * Энэ класс нь ExceptionHandlerInterface–ийг хэрэгжүүлж,
 * системд гарсан аливаа Exception / Error–ийг нэг цэгээс хүлээн авч,
 * зохих HTTP статус кодтой хариу үүсгэх зориулалттай, lightweight алдааны боловсруулагч юм.
 *
 * Үндсэн үүрэг:
 *  - Алдааны кодын дагуу HTTP статус тохируулах
 *  - ReasonPhrase тогтоосон эсэхийг шалгах
 *  - Алдааг серверийн error_log руу бичих
 *  - Хэрэглэгчид зориулсан энгийн HTML error page үүсгэх
 *  - CODESAUR_DEVELOPMENT = true үед trace мэдээлэл харуулах
 *
 * @package codesaur\Http\Application
 */
class ExceptionHandler implements ExceptionHandlerInterface
{
    /**
     * Exception / Throwable боловсруулах үндсэн функц.
     *
     * Application::use(new ExceptionHandler()) гэж бүртгэгдсэн үед
     * PHP-ийн set_exception_handler() механизмаар автоматаар дуудагдана.
     *
     * @param \Throwable $throwable Илэрсэн Exception / Error
     * @return void
     */
    public function exception(\Throwable $throwable)
    {
        // Error code, message, exception classname
        $code = $throwable->getCode();
        $message = $throwable->getMessage();
        $title = \get_class($throwable);

        /**
         * Алдааны код нь 0 биш үед HTTP статус код тохируулахыг оролдоно.
         *
         * Жишээ:
         *   throw new \Error("Not Found", 404);
         */
        if ($code != 0) {
            $status = "STATUS_$code";
            $reasonPhrase = ReasonPrhase::class;

            // ReasonPhrase class-д тухайн статус код байвал http_response_code() дуудах
            if (\defined("$reasonPhrase::$status") && !\headers_sent()) {
                \http_response_code($code);
            }

            // Title дээр кодыг нэмнэ → ErrorException 404
            $title .= " $code";
        }

        // Алдааны логийг системийн error_log руу бичих
        \error_log("$title: $message");

        /**
         * Хэрэглэгчид үзүүлэх энгийн HTML хуудас.
         * Энэ нь framework-н default error page юм.
         */
        $host = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off')
            || ($_SERVER['SERVER_PORT'] ?? null) == 443)
            ? 'https://' : 'http://';

        $host .= $_SERVER['HTTP_HOST'] ?? 'localhost';

        echo '<!doctype html>'
            . '<html lang="en">'
            . "<head><meta charset=\"utf-8\"><title>$title</title></head>"
            . "<body><h1>$title</h1><p>$message</p><hr><a href=\"$host\">$host</a></body>"
            . '</html>';

        /**
         * Хөгжүүлэлтийн горим идэвхтэй бол (CODESAUR_DEVELOPMENT = true)
         * алдааны trace-г debugger friendly байдлаар харуулна.
         */
        if (\defined('CODESAUR_DEVELOPMENT') && CODESAUR_DEVELOPMENT) {
            echo '<hr>';
            \var_dump($throwable->getTrace());
        }
    }
}
