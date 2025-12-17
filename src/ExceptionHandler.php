<?php

namespace codesaur\Http\Application;

use codesaur\Http\Message\ReasonPhrase;

/**
 * ExceptionHandler Class
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
 * @author Narankhuu
 * @since 1.0.0
 * @implements ExceptionHandlerInterface
 */
class ExceptionHandler implements ExceptionHandlerInterface
{
    /**
     * Exception / Throwable боловсруулах үндсэн функц.
     *
     * Application::use(new ExceptionHandler()) гэж бүртгэгдсэн үед
     * PHP-ийн set_exception_handler() механизмаар автоматаар дуудагдана.
     *
     * Энэ функц нь:
     * 1. Алдааны кодыг шалгаж HTTP статус код тохируулна
     * 2. Алдааг error_log руу бичнэ
     * 3. HTML error page үүсгэн хэрэглэгчид харуулна
     * 4. Development mode дээр stack trace харуулна
     *
     * @param \Throwable $throwable Илэрсэн Exception / Error объект
     * @return void
     *
     * @example
     * throw new \Error("Not Found", 404);
     * throw new \Exception("Server Error", 500);
     */
    public function exception(\Throwable $throwable): void
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
         *   throw new \Exception("Unauthorized", 401);
         */
        if ($code != 0) {
            $status = "STATUS_$code";
            $reasonPhraseClass = ReasonPhrase::class;

            // ReasonPhrase class-д тухайн статус код байвал http_response_code() дуудах
            if (\defined("$reasonPhraseClass::$status") && !\headers_sent()) {
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
        $host = $this->getHost();

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

    /**
     * HTTP host URL-г тодорхойлох.
     *
     * HTTPS эсвэл HTTP протоколыг автоматаар тодорхойлж,
     * host name-ийг нэгтгэн буцаана.
     *
     * @return string Protocol + host (жишээ: https://example.com)
     */
    private function getHost(): string
    {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off')
            || ($_SERVER['SERVER_PORT'] ?? null) == 443;

        $protocol = $isHttps ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return $protocol . $host;
    }
}
