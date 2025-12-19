# 🦖 codesaur/http-application

[![CI](https://github.com/codesaur-php/HTTP-Application/workflows/CI/badge.svg)](https://github.com/codesaur-php/HTTP-Application/actions)
[![PHP Version](https://img.shields.io/badge/php-%5E8.2.1-777BB4.svg?logo=php)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

**PSR-7 & PSR-15 нийцсэн хөнгөн, уян хатан HTTP Application цөм**

---

`codesaur/http-application` нь PSR-7 (HTTP Message) ба PSR-15 (HTTP Server RequestHandler/Middleware) стандартууд дээр суурилсан **минималист**, **өндөр уян хатан**, **middleware суурьтай** Application цөм юм.

Та хүсвэл:
- Router нэмэх  
- Middleware удирдах  
- Controller/action ашиглах  
- Closure route ашиглах  
- Exception handler бүртгэх  
- Custom request attributes ашиглах  

гэх мэтээр өөрийн хүссэн бүтэцтэй web application-ийг хэдхэн мөр кодоор босгох боломжтой.

---

# 🚀 Гол боломжууд

### ✔ PSR-7 стандартын ServerRequest + Response  
Request болон Response объектууд бүгд **immutable**, бүрэн стандартын дагуу.

### ✔ PSR-15 Middleware & RequestHandler гинжин бүтэц  
Middleware-үүд өөр хоорондоо сонгино шиг (before → action → after) ажиллана.

### ✔ Уян хатан Router интеграци  
Багц нь **codesaur/router**-ийг шууд дэмждэг.  

Dynamic, typed, multi-method маршрутуудыг амархан зарлана.

### ✔ Controller суурь класс  
PHP MVC хэв маягтай хөгжүүлэхэд тохиромжтой.

### ✔ Exception Handler  
Алдааны боловсруулалт. Development mode дээр trace харуулдаг. Хөгжүүлэгч өөрийн хүссэнээр сайжруулж болно.

### ✔ Хэт хөнгөн, хурдан  
Ямар ч framework-ийн суурь болгон ашиглах боломжтой.

---

# 📦 Суулгах

```
composer require codesaur/http-application
```

---

# 🧱 Архитектур

```
Application
 ├── Middleware stack (PSR-15 + Closure)
 ├── Router (codesaur/router)
 ├── ExceptionHandler
 └── Controller / Closure route executor
```

Application → Middleware-үүд → Match route → Controller/action/Closure → Response

---

# 📁 Төслийн файл бүтэц

```
HTTP-Application/
 ├── .github/
 │   └── workflows/
 │       └── ci.yml              # GitHub Actions CI/CD workflow
 ├── example/                    # Жишээ код
 │   ├── index.php               # Application boot script
 │   ├── ExampleRouter.php       # Router жишээ
 │   ├── ExampleController.php   # Controller жишээ
 │   ├── BeforeMiddleware.php    # Before middleware жишээ
 │   ├── AfterMiddleware.php     # After middleware жишээ
 │   ├── OnionMiddleware.php     # Onion middleware жишээ
 │   └── .htaccess               # Apache rewrite тохиргоо
 ├── src/                        # Эх код
 │   ├── Application.php         # Application цөм класс
 │   ├── Controller.php          # Controller суурь класс
 │   ├── ExceptionHandler.php    # Exception handler
 │   └── ExceptionHandlerInterface.php  # Exception handler интерфэйс
 ├── tests/                      # Тестүүд
 │   ├── ApplicationTest.php     # Application тестүүд
 │   ├── ControllerTest.php      # Controller тестүүд
 │   ├── ExceptionHandlerTest.php # ExceptionHandler тестүүд
 │   ├── EdgeCaseTest.php         # Edge case тестүүд
 │   ├── PerformanceTest.php      # Performance тестүүд
 │   ├── TestHelper.php           # Тест helper функцүүд
 │   └── Integration/
 │       └── ApplicationIntegrationTest.php  # Integration тестүүд
├── .gitignore                   # Git ignore файл
├── [API.md](API.md)             # API документаци
├── composer.json                # Composer тохиргоо
├── LICENSE                      # MIT лиценз
├── phpunit.xml                  # PHPUnit тохиргоо
├── README.md                    # Энэ файл
└── [REVIEW.md](REVIEW.md)       # Code review баримт бичиг
```

---

# 📝 Хэрэглээний жишээ

## 🔹 Application boot script (index.php)

```php
$application = new class extends Application {
    public function __construct() {
        parent::__construct();

        $this->use(new ExceptionHandler());
        $this->use(new BeforeMiddleware());
        $this->use(new AfterMiddleware());
        $this->use(new OnionMiddleware());
        $this->use(new ExampleRouter());

        $this->GET('/', [ExampleController::class, 'index']);
    }
};

$application->handle((new ServerRequest())->initFromGlobal());
```

---

# 🔗 Router жишээ

```php
$this->GET('/hello/{firstname}', [ExampleController::class, 'hello'])->name('hi');

$this->POST_PUT('/post-or-put', [ExampleController::class, 'post_put']);

$this->GET('/float/{float:number}', [ExampleController::class, 'float']);

$this->GET('/sum/{int:a}/{uint:b}', function ($req) {
    $a = $req->getAttribute('params')['a'];
    $b = $req->getAttribute('params')['b'];
    echo "$a + $b = " . ($a + $b);
});
```

---

# 🧭 Controller жишээ

```php
class ExampleController extends Controller
{
    public function hello(string $firstname)
    {
        $user = $firstname;

        $params = $this->getQueryParams();
        if (!empty($params['lastname'])) {
            $user .= " {$params['lastname']}";
        }

        echo "Hello $user!";
    }
}
```

---

# 🧅 Middleware жишээ (Onion модель)

### BeforeMiddleware → request шинээр attribute нэмэх  
### AfterMiddleware → request-ийн хугацааг хэвлэх  
### OnionMiddleware → before/after лог хэвлэх

```php
class OnionMiddleware implements MiddlewareInterface
{
    public function process($req, $handler): ResponseInterface
    {
        var_dump("i'm onion before");
        $res = $handler->handle($req);
        var_dump("i'm onion after");
        return $res;
    }
}
```

---

# ⚠ Алдааны боловсруулалт (ExceptionHandler)

```php
$this->use(new ExceptionHandler());
```

- Алдааны код байвал HTTP статус автоматаар тохируулна  
- Алдааг `error_log` руу бичнэ  
- HTML error page буцаана  
- Development mode дээр trace харагдана  

```php
define('CODESAUR_DEVELOPMENT', true);
```

---

# 🔍 Request боловсруулах дараалал

1. Middleware stack эхнээс нь дуудна  
2. Router → Match → Callback/Controller action  
3. Middleware stack буцаад дуусгана  
4. Response-г хэрэглэгч рүү дамжуулна  

---

# 🔧 Custom ExceptionHandler ашиглах

```php
class MyHandler implements ExceptionHandlerInterface {
    public function exception(Throwable $e) {
        http_response_code(500);
        echo "Custom error: " . $e->getMessage();
    }
}

$app->use(new MyHandler());
```

---

# 📘 Хөгжүүлэлтийн зөвлөмж

- PHP 8.2.1+ орчин  
- Apache + .htaccess rewrite тохиргоотой  (optional)
- Төсөлдөө MVC хэв маяг авахад маш тохиромжтой  

---

# 🧪 Тест ажиллуулах

Багц нь PHPUnit тестүүдтэй ирдэг. Доорх зааварчилгааны дагуу тестүүдийг ажиллуулж болно.

## Шаардлага

- PHP 8.2.1+ суулгасан байх
- Composer суулгасан байх
- PHP-XML, PHP-MBSTRING extensions идэвхжсэн байх (Linux/macOS)

## Алхам

1. **Dependencies суулгах:**
   ```bash
   composer install
   ```

2. **Тест ажиллуулах (OS-оос хамаарах команд):**

   **Windows (PowerShell/CMD):**
   ```powershell
   vendor\bin\phpunit
   ```

   **Linux/macOS:**
   ```bash
   vendor/bin/phpunit
   ```

3. **Coverage report үүсгэх:**
   
   **Windows:**
   ```powershell
   vendor\bin\phpunit --coverage-html coverage/html
   ```
   
   **Linux/macOS:**
   ```bash
   vendor/bin/phpunit --coverage-html coverage/html
   ```

4. **Тодорхой тест файл ажиллуулах:**
   
   **Windows:**
   ```powershell
   vendor\bin\phpunit tests/ApplicationTest.php
   ```
   
   **Linux/macOS:**
   ```bash
   vendor/bin/phpunit tests/ApplicationTest.php
   ```

## Тестүүдийн бүтэц

```
tests/
 ├── ApplicationTest.php      # Application классын тестүүд
 ├── ControllerTest.php       # Controller суурь классын тестүүд
 └── ExceptionHandlerTest.php # ExceptionHandler классын тестүүд
```

## GitHub Actions CI/CD

Төсөл нь GitHub Actions CI/CD workflow-тэй ирдэг. Push эсвэл Pull Request хийхэд автоматаар тестүүд ажиллана:

- **PHP хувилбарууд:** 8.2, 8.3, 8.4
- **Үйлдлийн системүүд:** Ubuntu, Windows, macOS
- **Coverage report:** Codecov руу автоматаар илгээгдэнэ

---

# 📄 Лиценз

Энэ төсөл MIT лицензтэй.

---

# 📚 Нэмэлт Документаци

- 📘 [API.md](API.md) - Бүрэн API удирдлага, бүх класс болон method-үүдийн дэлгэрэнгүй тайлбар (PHPDoc комментоос Cursor AI үүсгэв)
- 🔍 [REVIEW.md](REVIEW.md) - Код шалгалтын тайлан, код чанар, архитектур, PSR стандартууд (Cursor AI шинжилсэн)

---

# 👨‍💻 Зохиогч

Narankhuu  
📧 codesaur@gmail.com  
📲 [+976 99000287](https://wa.me/97699000287)  
🌐 https://github.com/codesaur  

---

# 🎯 Дүгнэлт

`codesaur/http-application` бол:
- Хөнгөн  
- Уян хатан  
- Стандарт мөрдсөн  
- Энгийн  
- Хурдан  

PHP дээр PSR стандарт нийцсэн өөрийн аппликейшн бүтэцтэй болохыг хүсвэл хамгийн тохиромжтой сонголт юм!
