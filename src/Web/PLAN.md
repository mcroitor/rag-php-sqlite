# Web-приложение RAG-PHP-SQLite — план реализации

> Статус: утверждённая схема архитектуры, код ещё не написан.
> Стек: REST API + ванильный JS-фронт, Skeleton CSS, `Mc\Router`, `Mc\Template`, `Mc\Sql`.

## Цели (по приоритету реализации)

1. Поиск по базе (query) — поле ввода, топ-N результатов: релевантность, источник, чанк.
2. Чат с LLM (ask) — RAG + генерация ответа Ollama с источниками.
3. Управление индексом — выбор папки, фоновая индексация, прогресс.
4. Статистика — документы, чанки, размер БД, статус Ollama.

## Слои

```
Presentation (Web): public/index.php + Mc\Router
   ├── PageController  — HTML-страницы через View/Mc\Template
   └── ApiController   — JSON через Router::json
        ↓
Application (App\Web\Services):
   SearchService, AskService, StatsService, JobManager
        ↓
Domain (App\Engine\Services): QueryService, RAGService, IndexingService
        ↓
Infrastructure: SQLiteStorage (PDO), EmbeddingCache, Ollama (Mc\Http), AppLogger
```

Правило: Web-слой не обращается к Engine напрямую, только через `App\Web\Services`.

## Структура папок

```
public/
  index.php                 — front controller (единственная точка входа)
  assets/css/               — normalize.css, skeleton.css, app.css
  assets/js/
    app.js                  — общий (nav, fetch-хелперы)
    search.js               — страница поиска
    chat.js                 — страница чата
    index.js                — управление индексом
    stats.js                — дашборд
src/Web/
  Application.php           — bootstrap, регистрация роутов
  Config.php                — пути, www, db() (Mc\Sql) — уже есть
  Core/
    View.php                — обёртка над Mc\Template — уже есть
    Response.php            — JSON/HTTP-хелпер (статусы, ошибки) — создать
  Controllers/
    PageController.php      — /, /search, /chat, /index, /stats — создать
    ApiController.php       — /api/* — создать
  Services/
    SearchService.php       — обёртка над QueryService — создать
    AskService.php          — обёртка над RAGService — создать
    StatsService.php        — счётчики из SQLiteStorage — создать
    JobManager.php          — запуск bin/index-worker.php + мониторинг — создать
  views/
    default.html            — каркас (есть)
    search.html, chat.html, index.html, stats.html — создать
```

## Контракт REST API v1

| Метод | Путь | Параметры | Ответ |
|---|---|---|---|
| GET  | /api/health    | —            | `{status, service}` (есть) |
| GET  | /api/search   | `q`, `top_k` | `{query, results:[{score, relevance, source, heading, text}]}` |
| POST | /api/ask      | `{q, top_k}` | `{answer, sources:[...]}` |
| GET  | /api/stats    | —            | `{documents, chunks, db_size, ollama:{status, model}}` |
| POST | /api/index    | `{dir, recursive, incremental}` | `{job_id}` |
| GET  | /api/jobs/{id}| —            | `{state: running/done/error, log:[...], stats?}` |

Ошибки единообразно: `{error: {code, message, status}}` (формат Mc\Router).

## Фоновая индексация

Протокол `bin/index-worker.php --job-id=<id> --dir=<path>`:
- пишет лог в `runtime/jobs/<id>.log`
- ставит маркеры `runtime/jobs/<id>.done` / `<id>.error`

JobManager: `POST /api/index` → создать job_id, запустить воркер через `proc_open`, вернуть job_id;
`GET /api/jobs/{id}` → читать лог + маркеры.

## Страницы

- `/search` (и `/`) — поиск
- `/chat` — вопрос → ответ LLM + источники
- `/index` — запуск фоновой индексации, прогресс/лог
- `/stats` — карточки статистики

## Ключевые решения

1. REST API + ванильный JS (без фреймворков).
2. Контроллеры — классы с DI; роуты регистрируются явно через `Router::get/post` в `Application.php` (без `#[Route]`-сканирования).
3. Один front controller `public/index.php`.
4. Web-таблицы (если появятся) — через `Mc\Sql\Crud`; таблицы движка не трогать.
5. Ошибки — try/catch в ApiController, маппинг `RAGException`/`EmbeddingException` → HTTP-статусы, лог через `AppLogger`.

## Шаги реализации

1. **Поиск:** SearchService + `GET /api/search` + страница `/search` + `search.js`
2. **Чат:** AskService + `POST /api/ask` + страница `/chat` + `chat.js`
3. **Индекс:** JobManager + `POST /api/index`, `GET /api/jobs/{id}` + страница `/index`
4. **Статистика:** StatsService + `GET /api/stats` + страница `/stats`
