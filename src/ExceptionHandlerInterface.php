<?php

namespace codesaur\Http\Application;

/**
 * ExceptionHandlerInterface
 *
 * Application түвшний алдааны боловсруулагч интерфэйс.
 *
 * Энэ интерфэйсийг хэрэгжүүлсэн класс нь
 * системд гарсан аливаа Exception / Error-ийг нэг цэгээс хүлээн авч
 * хүссэн хэлбэрээр боловсруулах боломжтой болно.
 *
 * Application::use(new ExceptionHandler()) гэж бүртгэх үед
 * PHP-ийн set_exception_handler() механизмаар автоматаар дуудагддаг.
 *
 * Зориулалт:
 *   - Алдааны логжуулалт
 *   - Custom error page үүсгэх
 *   - HTTP статус код тохируулах
 *   - Хөгжүүлэлтийн горимд stack trace харуулах
 *
 * @package codesaur\Http\Application
 * @author Narankhuu
 * @since 1.0.0
 */
interface ExceptionHandlerInterface
{
    /**
     * Гарсан Exception / Throwable-ийг боловсруулах функц.
     *
     * Энэ функц нь системд гарсан аливаа алдааг хүлээн авч,
     * HTTP статус код тохируулах, лог бичих, error page үүсгэх
     * зэрэг боловсруулалт хийх үүрэгтэй.
     *
     * Application::use(new ExceptionHandler()) гэж бүртгэх үед
     * PHP-ийн set_exception_handler() механизмаар автоматаар дуудагддаг.
     *
     * @param \Throwable $throwable Илэрсэн Exception эсвэл Error объект
     * @return void
     *
     * @example
     * public function exception(\Throwable $throwable): void {
     *     // HTTP статус код тохируулах
     *     $code = $throwable->getCode() ?: 500;
     *     \http_response_code($code);
     *
     *     // Лог бичих
     *     \error_log($throwable->getMessage());
     *
     *     // Error page харуулах
     *     echo "Error: " . $throwable->getMessage();
     * }
     */
    public function exception(\Throwable $throwable): void;
}
