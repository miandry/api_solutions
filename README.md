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
  -d '{"name": "newuser", "phone": "555-0199", "mail": "updated@example.com", "adress": {"province": "P", "city": "C", "location": "L"}}'
```

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
- **Example**:
```bash
curl -X GET "http://yoursite.com/api_solutions/api/v2/node/article?fields[]=title&fields[]=created"
```

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
