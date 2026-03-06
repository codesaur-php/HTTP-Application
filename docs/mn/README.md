# codesaur/http-application

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

# Гол боломжууд

### PSR-7 стандартын ServerRequest + Response
Request болон Response объектууд бүгд **immutable**, бүрэн стандартын дагуу.

### PSR-15 Middleware & RequestHandler гинжин бүтэц
Middleware-үүд өөр хоорондоо сонгино шиг (before -> action -> after) ажиллана.

### Уян хатан Router интеграци
Багц нь **codesaur/router**-ийг шууд дэмждэг.

Dynamic, typed, multi-method маршрутуудыг амархан зарлана.

### Controller суурь класс
PHP MVC хэв маягтай хөгжүүлэхэд тохиромжтой.

### Exception Handler
Алдааны боловсруулалт. Development mode дээр trace харуулдаг. Хөгжүүлэгч өөрийн хүссэнээр сайжруулж болно.

### Хэт хөнгөн, хурдан
Ямар ч framework-ийн суурь болгон ашиглах боломжтой.

---

# Суулгах

```
composer require codesaur/http-application
```

---

# Архитектур

```
Application
 +-- Middleware stack (PSR-15 + Closure)
 +-- Router (codesaur/router)
 +-- ExceptionHandler
 +-- Controller / Closure route executor
```

Application -> Middleware-үүд -> Match route -> Controller/action/Closure -> Response

---

# Хэрэглээний жишээ

## Application boot script (index.php)

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

# Router жишээ

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

# Controller жишээ

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

# Middleware жишээ (Onion модель)

### BeforeMiddleware -> request шинээр attribute нэмэх
### AfterMiddleware -> request-ийн хугацааг хэвлэх
### OnionMiddleware -> before/after лог хэвлэх

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

# Алдааны боловсруулалт (ExceptionHandler)

```php
$this->use(new ExceptionHandler());
```

- Алдааны код байвал HTTP статус автоматаар тохируулна
- Алдааг `error_log` руу бичнэ
- HTML error page буцаана
- Development mode дээр trace харагдана

```php
define('CODESAUR_DEVELOPMENT', true); // Development mode идэвхжүүлэх
```

---

# Request боловсруулах дараалал

1. Middleware stack эхнээс нь дуудна
2. Router -> Match -> Callback/Controller action
3. Middleware stack буцаад дуусгана
4. Response-г хэрэглэгч рүү дамжуулна

---

# Custom ExceptionHandler ашиглах

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

# Хөгжүүлэлтийн зөвлөмж

- PHP 8.2.1+ орчин
- Apache + .htaccess rewrite тохиргоотой (optional)
- Төсөлдөө MVC хэв маяг авахад маш тохиромжтой

---

## Тест ажиллуулах

### Composer Test Command-ууд

```bash
# Бүх тест ажиллуулах (Unit + Integration тестүүд)
composer test

# Зөвхөн Unit тестүүд ажиллуулах
composer test:unit

# Зөвхөн Integration тестүүд ажиллуулах
composer test:integration

# HTML coverage report үүсгэх (coverage/html directory дотор)
composer test:coverage

# Clover XML coverage report үүсгэх (CI/CD-д ашиглах)
composer test:coverage-clover
```

### Тестүүдийн мэдээлэл

- **Unit Tests**: Application, Controller, ExceptionHandler классуудын тест
- **Integration Tests**: Бүх компонентуудыг хамтдаа ашиглах integration тестүүд
- **Edge Case Tests**: Хязгаарийн тохиолдлуудын тест
- **Performance Tests**: Гүйцэтгэлийн тестүүд

### PHPUnit шууд ашиглах

Composer command-уудын оронд PHPUnit-ийг шууд ажиллуулж болно:

```bash
# Бүх тест ажиллуулах
vendor/bin/phpunit

# Зөвхөн Unit тестүүд
vendor/bin/phpunit --testsuite "HTTP Application Test Suite"

# Зөвхөн Integration тестүүд
vendor/bin/phpunit --testsuite "Integration Tests"

# Coverage report (Clover XML формат)
vendor/bin/phpunit --coverage-clover coverage.xml

# Coverage report (HTML формат)
vendor/bin/phpunit --coverage-html coverage/html

# Тодорхой тест файл ажиллуулах
vendor/bin/phpunit tests/ApplicationTest.php
```

**Windows хэрэглэгчид:** `vendor/bin/phpunit`-ийг `vendor\bin\phpunit.bat`-аар солино уу

## GitHub Actions CI/CD

Төсөл нь GitHub Actions CI/CD workflow-тэй. Push эсвэл Pull Request хийхэд автоматаар тестүүд ажиллана:

- **PHP хувилбарууд:** 8.2, 8.3, 8.4
- **Үйлдлийн системүүд:** Ubuntu, Windows, macOS
- **Coverage report:** Codecov руу автоматаар илгээгдэнэ

---

# Лиценз

Энэ төсөл MIT лицензтэй.

---

# Нэмэлт Документаци

- [API](api.md) - Бүрэн API удирдлага, бүх класс болон method-үүдийн дэлгэрэнгүй тайлбар (PHPDoc комментоос Cursor AI үүсгэв)
- [REVIEW](review.md) - Код шалгалтын тайлан, код чанар, архитектур, PSR стандартууд (Cursor AI шинжилсэн)

---

# Зохиогч

Narankhuu  
https://github.com/codesaur

---

# Дүгнэлт

`codesaur/http-application` бол:
- Хөнгөн
- Уян хатан
- Стандарт мөрдсөн
- Энгийн
- Хурдан

PHP дээр PSR стандарт нийцсэн өөрийн аппликейшн бүтэцтэй болохыг хүсвэл хамгийн тохиромжтой сонголт юм!
