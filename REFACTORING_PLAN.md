# Refactoring Plan - Convoy-Style Architecture

## Key Architectural Changes

### 1. API Client Changes
- Use REST API v2 endpoints (`/api2/json/...`)
- Use `application/json` content type (not form-data)
- Shorter timeouts (30s total, 5s connect)
- Fire-and-forget approach (no task waiting)

### 2. Repository Pattern
- Base repository with HTTP client management
- Separate repositories for different concerns
- Clean URL parameter handling
- Use Laravel's `throw()` for error handling

### 3. Timeout Strategy
- 15-30s API timeout (not 600s)
- 5s connect timeout
- Don't wait for task completion
- Operations return immediately

### 4. Service Layer
- Simple synchronous operations
- Fire API call and return
- Don't block waiting for PVE tasks

## License Compliance
- Keep original naming (Jotunheim/Midgard vs Convoy)
- Use different code structure
- Different file organization
- Own implementation of similar concepts
