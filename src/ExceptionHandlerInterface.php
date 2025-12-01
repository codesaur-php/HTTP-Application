<?php

namespace codesaur\Http\Application;

/**
 * Interface ExceptionHandlerInterface
 *
 * Application түвшний алдааны боловсруулагч интерфэйс.
 *
 * Энэ интерфэйсийг хэрэгжүүлсэн класс нь
 * системд гарсан аливаа Exception / Error–ийг нэг цэгээс хүлээн авч
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
 */
interface ExceptionHandlerInterface
{
    /**
     * Гарсан Exception / Throwable-ийг боловсруулах функц.
     *
     * @param \Throwable $throwable Илэрсэн Exception эсвэл Error объект
     * @return void
     */
    public function exception(\Throwable $throwable);
}
