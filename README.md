# 🦖 codesaur/http-application
**PHP 8.2+ · PSR-7 & PSR-15 нийцсэн хөнгөн, уян хатан HTTP Application цөм**

`codesaur/http-application` нь PSR-7 (HTTP Message) ба PSR-15 (HTTP Server RequestHandler/Middleware) стандартууд дээр суурилсан **минималист**, **өндөр уян хатан**, **middleware суурьтай** Application цөм юм.

Та хүсвэл:
- Router нэмэх  
- Middleware удирдах  
- Controller/action ашиглах  
- Closure route ашиглах  
- Exception handler бүртгэх  
- Custom request attributes ашиглах  

гэ мэтээр өөрийн хүссэн бүтэцтэй web application-ийг хэдхэн мөр кодоор босгох боломжтой.

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
Алдааны统一 боловсруулалт. Development mode дээр trace харуулдаг.

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

# 📁 Жишээ төслийн бүтэц (`example/`)

```
example/
 ├── index.php
 ├── ExampleRouter.php
 ├── ExampleController.php
 ├── BeforeMiddleware.php
 ├── AfterMiddleware.php
 ├── OnionMiddleware.php
 └── .htaccess
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
- Сайхан HTML error page буцаана  
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

- PHP 8.2+ орчин  
- Apache + .htaccess rewrite тохиргоотой  
- Төсөлдөө MVC хэв маяг авахад маш тохиромжтой  

---

# 📄 Лиценз

Энэ төсөл MIT лицензтэй.

---

# 👨‍💻 Хөгжүүлэгч

Narankhuu  
📧 codesaur@gmail.com  
📱 +976 99000287  
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
