# Example - бүртгэгдсэн route-уудын хүснэгт

`example/index.php` дотор anonymous Application класс хоёр Router-ийг `use()`-ээр
бүртгэдэг: эхэлж `ExampleRouter` (модулийн router), дараа нь нэрлэгдээгүй `$inline`
router. Match-ийн эрэмбэ нь registration order (first-added-wins) тул эхэлж нэмсэн
`ExampleRouter` урьд шалгагдана.

Энэ жишээ `mount()` дууддаггүй тул бүх route root-д (prefix-гүй) байна.

## ExampleRouter (`$this->use(new ExampleRouter())`)

| # | Method(s) | Path | Handler | name() |
|---|-----------|------|---------|--------|
| 1 | GET | `/hello/{firstname}` | `ExampleController::hello` | `hi` |
| 2 | POST, PUT | `/post-or-put` | `ExampleController::post_put` | - |
| 3 | GET, POST, PUT, DELETE, OPTIONS | `/echo/{singleword}` | Closure | `echo` |
| 4 | GET | `/float/{float:number}` | `ExampleController::float` | `float` |
| 5 | GET | `/sum/{int:a}/{uint:b}` | Closure | `sum` |

## Inline router (`$this->use($inline)`)

| # | Method(s) | Path | Handler | name() | Тэмдэглэл |
|---|-----------|------|---------|--------|-----------|
| 6 | GET | `/` | `ExampleController::index` | - | - |
| 7 | GET | `/home` | Closure -> `ExampleController::index` | `home` | - |
| 8 | GET | `/hello/{firstname}/{lastname}` | Closure -> `ExampleController::hello` | `hello` | 2 параметр |
| 9 | POST | `/hello/post` | Closure -> `ExampleController::hello` | - | firstname хоосон бол `\Error` шиднэ |
| 10 | GET | `/guarded` | Closure -> `ExampleController::index` | `guarded` | Per-route middleware (Closure) хавсаргасан |

## Тэмдэглэлүүд

- **Параметрийн төрөл:** `{int:a}`, `{uint:b}`, `{float:number}` - типтэй параметр;
  `{firstname}`, `{singleword}` - типгүй (string). Буруу төрөл дамжуулбал
  `generate()` нь `InvalidArgumentException` шиднэ.
- **Хоёр өөр `/hello`:** ExampleRouter-ийн `/hello/{firstname}` (1 параметр, name `hi`)
  ба inline-ийн `/hello/{firstname}/{lastname}` (2 параметр, name `hello`) нь
  өөр өөр path тул зөрчилгүй.
- **Per-route middleware:** зөвхөн route #10 (`/guarded`) дээр хавсаргасан -
  global middleware stack-аас тусдаа, зөвхөн тэр route дээр л ажиллана.
- **Global middleware** (бүх route-д хамаарна): `BeforeMiddleware`,
  `AfterMiddleware`, `OnionMiddleware`, мөн route гүйцэтгэлийн мэдээлэл хэвлэх
  tail Closure - бүгд `$this->use(...)`-ээр бүртгэгдсэн.
