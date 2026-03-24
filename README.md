# API Solutions Module

The `API Solutions` module provides a unified RESTful API for Drupal, combining CRUD operations, user management, and content retrieval with **token-based authentication** using HTTP-Only cookies.

## Base URL
All routes are prefixed with: `/api_solutions`

## Authentication

Authentication uses `field_api_token` stored on the user entity, delivered via an **HTTP-Only cookie** named `auth_token`.

### Auth flow

1. **Login or Register** — the server generates a token, stores it in `field_api_token`, and sets an `auth_token` HTTP-Only cookie (30 days).
2. **Authenticated requests** — the cookie is sent automatically by the browser. Alternatively, use `Authorization: Bearer <TOKEN>` header or include `token` in the POST body.
3. **Logout** — the server clears `field_api_token` and removes the cookie.
4. **Check Auth** — verify if the current session is still valid.

### Token resolution order (for `/save`)
1. `auth_token` cookie
2. `Authorization: Bearer` header
3. `token` field in POST body

### Role-based permissions
The `/save` endpoint enforces Drupal's content permissions per entity type/bundle:
- **Node (create)**: requires `create {bundle} content`
- **Node (update)**: requires `edit any {bundle} content` or `edit own {bundle} content`
- **Taxonomy term**: requires `create terms in {bundle}` or `edit terms in {bundle}`
- **Comment**: requires `post comments`
- **Administrator** role bypasses all checks

---

## Endpoints

### 1. User Management

#### Login
- **Endpoint**: `POST /user/login`
- **Sets**: `auth_token` HTTP-Only cookie
- **Response**: `{ status, token, id, name, mail, roles, data }`
```bash
curl -X POST http://yoursite.com/api_solutions/user/login \
  -H "Content-Type: application/json" \
  -d '{"name": "user1", "pass": "secret"}'
```

#### Register
- **Endpoint**: `POST /user/register`
- **Sets**: `auth_token` HTTP-Only cookie
- **Response**: `{ status, token, id, name, mail }`
```bash
curl -X POST http://yoursite.com/api_solutions/user/register \
  -H "Content-Type: application/json" \
  -d '{"name": "newuser", "pass": "abc123", "mail": "new@example.com", "role": "editor"}'
```

#### Logout
- **Endpoint**: `POST /user/logout`
- **Clears**: `auth_token` cookie + `field_api_token`
```bash
curl -X POST http://yoursite.com/api_solutions/user/logout \
  -b "auth_token=<TOKEN>"
```

#### Check Auth
- **Endpoint**: `GET /user/check-auth`
- **Response**: `{ authenticated, user: { id, name, mail, roles, data } }`
```bash
curl -X GET http://yoursite.com/api_solutions/user/check-auth \
  -b "auth_token=<TOKEN>"
```

#### Generate Token (legacy)
- **Endpoint**: `POST /generate/token`
- **Response**: `{ status, token, id }`
```bash
curl -X POST http://yoursite.com/api_solutions/generate/token \
  -H "Content-Type: application/json" \
  -d '{"name": "admin", "password": "pass"}'
```

#### User Edit
- **Endpoint**: `POST /user/edit`
- **Auth**: Cookie or Bearer token
```bash
curl -X POST http://yoursite.com/api_solutions/user/edit \
  -H "Authorization: Bearer <TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "current_username",
    "email": "updated@example.com",
    "new_name": "new_username",
    "password": "new_secure_password"
  }'
```
> `name` is required for identification. You can update `email`, `new_name` (username), and `password`.

#### Forgot Password
- **Endpoint**: `POST /user/forgot-password`
```bash
curl -X POST http://yoursite.com/api_solutions/user/forgot-password \
  -H "Content-Type: application/json" \
  -d '{"email": "user@example.com"}'
```

---

### 2. CRUD Operations

#### Save Entity
- **Endpoint**: `POST /save`
- **Auth**: Cookie / Bearer / body token
- **Permission**: Checked per entity_type and bundle (see Role-based permissions above)
- **Note**: `uid` is auto-set from authenticated user for nodes if not provided
```bash
curl -X POST http://yoursite.com/api_solutions/save \
  -b "auth_token=<TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{"entity_type": "node", "bundle": "article", "title": "New Title"}'
```

---

### 3. Content Retrieval

#### List Taxonomy Terms
- **Endpoint**: `GET /api/v1/term/{vid}`
```bash
curl -X GET http://yoursite.com/api_solutions/api/v1/term/tags
```

#### Simple List (v1)
- **Endpoint**: `GET /api/v1/list`
```bash
curl -X GET "http://yoursite.com/api_solutions/api/v1/list?entitype=node&bundle=article&offset=10&pager=1"
```

#### Advanced List (v2)
- **Endpoint**: `GET /api/v2/{entitype}/{bundle}`
- **Parameters**:
    - `fields[]`: Field names to include in the response
    - `sort_by`: Field to sort by (e.g., `nid`, `created`)
    - `sort_order`: `ASC` or `DESC`
    - `filters[field_name][val]`: Filter by field value
    - `filters[field_name][op]`: Operator (`CONTAINS`, `>`, `<`, etc.)
    - `range`: Number of results to return
    - `changes[old_name]=new_name`: Rename an output field
    - `values[field_name]=constant_value`: Inject a fixed value into results
```bash
curl -X GET "http://yoursite.com/api_solutions/api/v2/node/article?filters[title][val]=Drupal&filters[title][op]=CONTAINS&fields[]=title&fields[]=body&range=20"
```
> **Tip**: Use `fields[]` to reduce payload size. Each entry corresponds to a Drupal field machine name.

#### Entity Details (v2)
- **Endpoint**: `GET /api/v2/{entitype}/{bundle}/{id}`
```bash
curl -X GET http://yoursite.com/api_solutions/api/v2/node/article/123
```

#### User List (v2)
- **Endpoint**: `GET /api/v2/users`
```bash
curl -X GET "http://yoursite.com/api_solutions/api/v2/users?page=1&limit=5"
```

#### Menu Tree
- **Endpoint**: `GET /api/v1/menu`
```bash
curl -X GET http://yoursite.com/api_solutions/api/v1/menu
```

---

### 4. Utilities

#### File Upload
- **Endpoint**: `POST /action/uploader`
```bash
curl -X POST http://yoursite.com/api_solutions/action/uploader \
  -F "files[]=@/path/to/your/image.jpg"
```

---

## Services

| Service | Class | Description |
|---|---|---|
| `api_solutions.crud` | `CRUDService` | Entity CRUD operations |
| `api_solutions.api_crud` | `APIService` | Token generation, validation, invalidation |
| `api_solutions.manager` | `ApiJsonParser` | Query builder for list endpoints |
