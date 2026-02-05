# API Solutions Module

The `API Solutions` module provides a unified RESTful API for Drupal, combining CRUD operations, user management, and content retrieval.

## Base URL
All routes are prefixed with: `/api_solutions`

## Authentication

Most protected endpoints require **Bearer Token** authentication.

1. **Get Token**: Send a POST request to `/api_solutions/generate/token` with credentials.
2. **Use Token**: Include the header `Authorization: Bearer <YOUR_TOKEN>` in your requests.

---

## Endpoints and Examples

### 1. User Management

#### Generate Token
- **Endpoint**: `/generate/token`
- **Method**: `POST`
- **Example**:
```bash
curl -X POST http://yoursite.com/api_solutions/generate/token \
  -H "Content-Type: application/json" \
  -d '{"name": "admin", "password": "pass"}'
```

#### User Login
- **Endpoint**: `/user/login`
- **Method**: `POST`
- **Example**:
```bash
curl -X POST http://yoursite.com/api_solutions/user/login \
  -H "Content-Type: application/json" \
  -d '{"name": "user1", "pass": "secret"}'
```

#### User Register
- **Endpoint**: `/user/register`
- **Method**: `POST`
- **Example**:
```bash
curl -X POST http://yoursite.com/api_solutions/user/register \
  -H "Content-Type: application/json" \
  -d '{"name": "newuser", "pass": "abc123", "mail": "new@example.com"}'
```

#### User Edit
- **Endpoint**: `/user/edit`
- **Method**: `POST`
- **Auth**: `Bearer`
- **Example**:
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
> [!NOTE]
> `name` is required for identification. You can update `email`, `new_name` (username), and `password`.

#### Forgot Password
- **Endpoint**: `/user/forgot-password`
- **Method**: `POST`
- **Example**:
```bash
curl -X POST http://yoursite.com/api_solutions/user/forgot-password \
  -H "Content-Type: application/json" \
  -d '{"email": "user@example.com"}'
```

---

### 2. CRUD Operations

#### Save Entity
- **Endpoint**: `/save`
- **Method**: `POST`
- **Auth**: `Bearer`
- **Example**:
```bash
curl -X POST http://yoursite.com/api_solutions/save \
  -H "Authorization: Bearer <TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{"entity_type": "node", "bundle": "article", "title": "New Title", "author": "admin"}'
```

---

### 3. Content Retrieval

#### List Taxonomy Terms
- **Endpoint**: `/api/v1/term/{vid}`
- **Method**: `GET`
- **Example**:
```bash
curl -X GET http://yoursite.com/api_solutions/api/v1/term/tags
```

#### Simple List (v1)
- **Endpoint**: `/api/v1/list`
- **Method**: `GET`
- **Example**:
```bash
curl -X GET "http://yoursite.com/api_solutions/api/v1/list?entitype=node&bundle=article&offset=10&pager=1"
```

#### Advanced List (v2)
- **Endpoint**: `/api/v2/{entitype}/{bundle}`
- **Method**: `GET`
- **Parameters**: 
    - `fields[]`: (Optional) An array of field names to include in the response.
    - `sort[val]`: (Optional) Field to sort by (e.g., `nid`, `created`).
    - `sort[op]`: (Optional) Sort direction: `ASC` or `DESC`.
    - `filters[field_name][val]`: (Optional) Filter by field value.
    - `filters[field_name][op]`: (Optional) Operator (e.g., `CONTAINS`, `>`, `<`).
- **Example**:
```bash
# Get articles with "Drupal" in the title, ordered by creation date
curl -X GET "http://yoursite.com/api_solutions/api/v2/node/article?filters[title][val]=Drupal&filters[title][op]=CONTAINS&sort[val]=created&sort[op]=DESC"
```
> [!TIP]
> **Field Filtering**: By default, the API might return a large subset of fields. Use the `fields[]` parameter to specify exactly what you need. This significantly reduces payloads for mobile apps or high-traffic integrations.
> 
> **How it works**:
> - Each `fields[]` entry corresponds to a Drupal machine name (e.g., `body`, `field_image`, `uid`).
> - You can add as many as needed: `?fields[]=title&fields[]=body&fields[]=field_tags`.

#### Entity Details (v2)
- **Endpoint**: `/api/v2/{entitype}/{bundle}/{id}`
- **Method**: `GET`
- **Example**:
```bash
curl -X GET http://yoursite.com/api_solutions/api/v2/node/article/123
```

#### User List (v2)
- **Endpoint**: `/api/v2/users`
- **Method**: `GET`
- **Example**:
```bash
curl -X GET "http://yoursite.com/api_solutions/api/v2/users?page=1&limit=5"
```

#### Menu Tree
- **Endpoint**: `/api/v1/menu`
- **Method**: `GET`
- **Example**:
```bash
curl -X GET http://yoursite.com/api_solutions/api/v1/menu
```

---

### 4. Utilities

#### File Upload
- **Endpoint**: `/action/uploader`
- **Method**: `POST`
- **Example**:
```bash
curl -X POST http://yoursite.com/api_solutions/action/uploader \
  -F "files[]=@/path/to/your/image.jpg"
```
