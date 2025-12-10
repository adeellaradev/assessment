# Frontend Authentication Implementation Guide

This guide explains how to implement login and signup functionality on the frontend to work with this Laravel backend API.

## Table of Contents
- [API Endpoints](#api-endpoints)
- [Authentication Flow](#authentication-flow)
- [Implementation Examples](#implementation-examples)
- [API Reference](#api-reference)
- [Error Handling](#error-handling)

---

## API Endpoints

### Base URL
```
http://localhost:8000/api
```

### Available Endpoints

| Method | Endpoint | Auth Required | Description |
|--------|----------|---------------|-------------|
| POST | `/login` | No | Login user and get access token |
| POST | `/logout` | Yes | Logout user and revoke token |
| GET | `/profile` | Yes | Get user profile with balance and assets |

---

## Authentication Flow

### 1. Login Flow

```
User enters credentials
    ↓
POST /api/login
    ↓
Receive token + user data
    ↓
Store token (localStorage/sessionStorage)
    ↓
Redirect to dashboard
```

### 2. Authenticated Requests Flow

```
User makes request
    ↓
Attach token to Authorization header
    ↓
Backend validates token
    ↓
Return data or 401 Unauthorized
```

### 3. Logout Flow

```
User clicks logout
    ↓
POST /api/logout with token
    ↓
Clear stored token
    ↓
Redirect to login page
```

---

## Implementation Examples

### JavaScript (Vanilla/Fetch API)

#### Login Function

```javascript
async function login(email, password) {
  try {
    const response = await fetch('http://localhost:8000/api/login', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify({
        email: email,
        password: password,
      }),
    });

    const data = await response.json();

    if (!response.ok) {
      throw new Error(data.message || 'Login failed');
    }

    // Store token
    localStorage.setItem('authToken', data.token);
    localStorage.setItem('user', JSON.stringify(data.user));

    return data;
  } catch (error) {
    console.error('Login error:', error);
    throw error;
  }
}
```

#### Logout Function

```javascript
async function logout() {
  try {
    const token = localStorage.getItem('authToken');

    const response = await fetch('http://localhost:8000/api/logout', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Authorization': `Bearer ${token}`,
      },
    });

    // Clear stored data
    localStorage.removeItem('authToken');
    localStorage.removeItem('user');

    return true;
  } catch (error) {
    console.error('Logout error:', error);
    // Still clear local data even if request fails
    localStorage.removeItem('authToken');
    localStorage.removeItem('user');
  }
}
```

#### Get Profile Function

```javascript
async function getProfile() {
  try {
    const token = localStorage.getItem('authToken');

    const response = await fetch('http://localhost:8000/api/profile', {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Authorization': `Bearer ${token}`,
      },
    });

    if (!response.ok) {
      if (response.status === 401) {
        // Token expired or invalid - redirect to login
        localStorage.removeItem('authToken');
        localStorage.removeItem('user');
        window.location.href = '/login';
        return;
      }
      throw new Error('Failed to fetch profile');
    }

    const data = await response.json();
    return data;
  } catch (error) {
    console.error('Profile error:', error);
    throw error;
  }
}
```

---

### React Implementation

#### Authentication Context

```javascript
// AuthContext.js
import React, { createContext, useState, useContext, useEffect } from 'react';

const AuthContext = createContext(null);

export const AuthProvider = ({ children }) => {
  const [user, setUser] = useState(null);
  const [token, setToken] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    // Load token and user from storage on mount
    const storedToken = localStorage.getItem('authToken');
    const storedUser = localStorage.getItem('user');

    if (storedToken && storedUser) {
      setToken(storedToken);
      setUser(JSON.parse(storedUser));
    }
    setLoading(false);
  }, []);

  const login = async (email, password) => {
    try {
      const response = await fetch('http://localhost:8000/api/login', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: JSON.stringify({ email, password }),
      });

      const data = await response.json();

      if (!response.ok) {
        throw new Error(data.message || 'Login failed');
      }

      // Save token and user
      localStorage.setItem('authToken', data.token);
      localStorage.setItem('user', JSON.stringify(data.user));

      setToken(data.token);
      setUser(data.user);

      return data;
    } catch (error) {
      throw error;
    }
  };

  const logout = async () => {
    try {
      if (token) {
        await fetch('http://localhost:8000/api/logout', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'Authorization': `Bearer ${token}`,
          },
        });
      }
    } catch (error) {
      console.error('Logout error:', error);
    } finally {
      // Clear state and storage
      localStorage.removeItem('authToken');
      localStorage.removeItem('user');
      setToken(null);
      setUser(null);
    }
  };

  const value = {
    user,
    token,
    login,
    logout,
    isAuthenticated: !!token,
    loading,
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
};

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within AuthProvider');
  }
  return context;
};
```

#### Login Component

```javascript
// LoginPage.js
import React, { useState } from 'react';
import { useAuth } from './AuthContext';
import { useNavigate } from 'react-router-dom';

function LoginPage() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  const { login } = useAuth();
  const navigate = useNavigate();

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setLoading(true);

    try {
      await login(email, password);
      navigate('/dashboard');
    } catch (err) {
      setError(err.message || 'Login failed. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="login-container">
      <h2>Login</h2>
      <form onSubmit={handleSubmit}>
        {error && <div className="error-message">{error}</div>}

        <div className="form-group">
          <label htmlFor="email">Email:</label>
          <input
            type="email"
            id="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            required
            disabled={loading}
          />
        </div>

        <div className="form-group">
          <label htmlFor="password">Password:</label>
          <input
            type="password"
            id="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
            disabled={loading}
          />
        </div>

        <button type="submit" disabled={loading}>
          {loading ? 'Logging in...' : 'Login'}
        </button>
      </form>
    </div>
  );
}

export default LoginPage;
```

#### Protected Route Component

```javascript
// ProtectedRoute.js
import React from 'react';
import { Navigate } from 'react-router-dom';
import { useAuth } from './AuthContext';

function ProtectedRoute({ children }) {
  const { isAuthenticated, loading } = useAuth();

  if (loading) {
    return <div>Loading...</div>;
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />;
  }

  return children;
}

export default ProtectedRoute;
```

#### API Service with Interceptor

```javascript
// apiService.js
const API_BASE_URL = 'http://localhost:8000/api';

async function apiRequest(endpoint, options = {}) {
  const token = localStorage.getItem('authToken');

  const config = {
    ...options,
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      ...(token && { 'Authorization': `Bearer ${token}` }),
      ...options.headers,
    },
  };

  try {
    const response = await fetch(`${API_BASE_URL}${endpoint}`, config);
    const data = await response.json();

    if (!response.ok) {
      if (response.status === 401) {
        // Token expired - redirect to login
        localStorage.removeItem('authToken');
        localStorage.removeItem('user');
        window.location.href = '/login';
        throw new Error('Session expired. Please login again.');
      }
      throw new Error(data.message || 'Request failed');
    }

    return data;
  } catch (error) {
    console.error('API Error:', error);
    throw error;
  }
}

export const api = {
  login: (email, password) =>
    apiRequest('/login', {
      method: 'POST',
      body: JSON.stringify({ email, password }),
    }),

  logout: () =>
    apiRequest('/logout', {
      method: 'POST',
    }),

  getProfile: () => apiRequest('/profile'),

  getOrders: (symbol) => apiRequest(`/orders?symbol=${symbol}`),

  createOrder: (orderData) =>
    apiRequest('/orders', {
      method: 'POST',
      body: JSON.stringify(orderData),
    }),

  cancelOrder: (orderId) =>
    apiRequest(`/orders/${orderId}/cancel`, {
      method: 'POST',
    }),
};
```

---

### Vue.js Implementation

#### Vuex Store (Authentication Module)

```javascript
// store/auth.js
import axios from 'axios';

const API_BASE_URL = 'http://localhost:8000/api';

export default {
  namespaced: true,

  state: {
    user: JSON.parse(localStorage.getItem('user')) || null,
    token: localStorage.getItem('authToken') || null,
  },

  getters: {
    isAuthenticated: (state) => !!state.token,
    currentUser: (state) => state.user,
  },

  mutations: {
    SET_TOKEN(state, token) {
      state.token = token;
      if (token) {
        localStorage.setItem('authToken', token);
      } else {
        localStorage.removeItem('authToken');
      }
    },

    SET_USER(state, user) {
      state.user = user;
      if (user) {
        localStorage.setItem('user', JSON.stringify(user));
      } else {
        localStorage.removeItem('user');
      }
    },
  },

  actions: {
    async login({ commit }, { email, password }) {
      try {
        const response = await axios.post(`${API_BASE_URL}/login`, {
          email,
          password,
        });

        const { token, user } = response.data;

        commit('SET_TOKEN', token);
        commit('SET_USER', user);

        // Set default Authorization header
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;

        return response.data;
      } catch (error) {
        commit('SET_TOKEN', null);
        commit('SET_USER', null);
        throw error;
      }
    },

    async logout({ commit }) {
      try {
        await axios.post(`${API_BASE_URL}/logout`);
      } catch (error) {
        console.error('Logout error:', error);
      } finally {
        commit('SET_TOKEN', null);
        commit('SET_USER', null);
        delete axios.defaults.headers.common['Authorization'];
      }
    },

    async getProfile({ commit, state }) {
      try {
        const response = await axios.get(`${API_BASE_URL}/profile`);
        return response.data;
      } catch (error) {
        if (error.response?.status === 401) {
          commit('SET_TOKEN', null);
          commit('SET_USER', null);
        }
        throw error;
      }
    },
  },
};
```

#### Axios Interceptor

```javascript
// plugins/axios.js
import axios from 'axios';
import store from '@/store';
import router from '@/router';

axios.defaults.baseURL = 'http://localhost:8000/api';

// Request interceptor
axios.interceptors.request.use(
  (config) => {
    const token = store.state.auth.token;
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// Response interceptor
axios.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      store.commit('auth/SET_TOKEN', null);
      store.commit('auth/SET_USER', null);
      router.push('/login');
    }
    return Promise.reject(error);
  }
);

export default axios;
```

#### Login Component (Vue)

```vue
<!-- LoginPage.vue -->
<template>
  <div class="login-container">
    <h2>Login</h2>
    <form @submit.prevent="handleLogin">
      <div v-if="error" class="error-message">{{ error }}</div>

      <div class="form-group">
        <label for="email">Email:</label>
        <input
          type="email"
          id="email"
          v-model="email"
          required
          :disabled="loading"
        />
      </div>

      <div class="form-group">
        <label for="password">Password:</label>
        <input
          type="password"
          id="password"
          v-model="password"
          required
          :disabled="loading"
        />
      </div>

      <button type="submit" :disabled="loading">
        {{ loading ? 'Logging in...' : 'Login' }}
      </button>
    </form>
  </div>
</template>

<script>
import { mapActions } from 'vuex';

export default {
  name: 'LoginPage',

  data() {
    return {
      email: '',
      password: '',
      error: '',
      loading: false,
    };
  },

  methods: {
    ...mapActions('auth', ['login']),

    async handleLogin() {
      this.error = '';
      this.loading = true;

      try {
        await this.login({
          email: this.email,
          password: this.password,
        });

        this.$router.push('/dashboard');
      } catch (error) {
        this.error = error.response?.data?.message || 'Login failed';
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>
```

---

## API Reference

### POST `/api/login`

Login a user and receive an authentication token.

**Request:**
```json
{
  "email": "john@example.com",
  "password": "password"
}
```

**Success Response (200):**
```json
{
  "message": "Login successful",
  "token": "1|g7OEbuc3XpTaLoshKCh6WJucCjNEztg8eh0s9uG8e7b9b1d2",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com"
  }
}
```

**Error Response (401):**
```json
{
  "message": "Invalid credentials"
}
```

---

### POST `/api/logout`

Logout the current user and revoke their token.

**Headers:**
```
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
  "message": "Logout successful"
}
```

---

### GET `/api/profile`

Get the authenticated user's profile with balance and assets.

**Headers:**
```
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "balance": "10000.00000000"
  },
  "assets": [
    {
      "symbol": "BTC",
      "amount": "21.55804002",
      "locked_amount": "0.50000000",
      "available_amount": "21.05804002"
    },
    {
      "symbol": "ETH",
      "amount": "82.30637339",
      "locked_amount": "0.00000000",
      "available_amount": "82.30637339"
    }
  ]
}
```

**Error Response (401):**
```json
{
  "message": "Unauthenticated."
}
```

---

## Error Handling

### Common HTTP Status Codes

| Code | Meaning | Action |
|------|---------|--------|
| 200 | OK | Request successful |
| 201 | Created | Resource created successfully |
| 400 | Bad Request | Validation error - check request data |
| 401 | Unauthorized | Token missing, invalid, or expired - redirect to login |
| 422 | Unprocessable Entity | Validation failed - display field errors |
| 500 | Server Error | Server issue - show generic error message |

### Handling Validation Errors (422)

Laravel returns validation errors in this format:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email field is required."],
    "password": ["The password field is required."]
  }
}
```

**Example handling:**

```javascript
try {
  await login(email, password);
} catch (error) {
  if (error.response?.status === 422) {
    const errors = error.response.data.errors;
    // Display errors for each field
    Object.keys(errors).forEach((field) => {
      console.error(`${field}: ${errors[field].join(', ')}`);
    });
  }
}
```

---

## Test Credentials

The seeded database includes the following test users:

| Email | Password | Balance |
|-------|----------|---------|
| john@example.com | password | $10,000.00 |
| jane@example.com | password | $15,000.00 |
| bob@example.com | password | $20,000.00 |
| alice@example.com | password | $25,000.00 |
| charlie@example.com | password | $30,000.00 |

---

## Security Best Practices

1. **Store tokens securely**
   - Use `localStorage` or `sessionStorage`
   - Never store in cookies without `httpOnly` and `secure` flags
   - Consider using `sessionStorage` for higher security (clears on tab close)

2. **HTTPS in production**
   - Always use HTTPS in production
   - Tokens sent over HTTP can be intercepted

3. **Token expiration**
   - Handle 401 responses gracefully
   - Redirect to login when token expires
   - Consider implementing refresh tokens for better UX

4. **CORS Configuration**
   - Backend should have proper CORS headers configured
   - For Laravel, update `config/cors.php`:
   ```php
   'paths' => ['api/*'],
   'allowed_origins' => ['http://localhost:3000'], // Your frontend URL
   'allowed_methods' => ['*'],
   'allowed_headers' => ['*'],
   'supports_credentials' => false,
   ```

5. **Input Validation**
   - Always validate user input on frontend
   - Don't rely solely on backend validation
   - Sanitize input before sending

6. **Logout on token error**
   - Clear local data when receiving 401 responses
   - Redirect user to login page

---

## Complete Frontend Authentication Checklist

- [ ] Create login form with email and password fields
- [ ] Implement login function that calls `/api/login`
- [ ] Store returned token in localStorage/sessionStorage
- [ ] Store user data in state management (Context/Vuex/Redux)
- [ ] Create logout function that calls `/api/logout`
- [ ] Clear stored token and user data on logout
- [ ] Add Authorization header to all authenticated requests
- [ ] Implement axios/fetch interceptor for automatic token attachment
- [ ] Handle 401 responses (token expired/invalid)
- [ ] Create protected route component
- [ ] Redirect unauthenticated users to login page
- [ ] Display user profile data from `/api/profile`
- [ ] Show loading states during API calls
- [ ] Display error messages appropriately
- [ ] Test with provided test credentials

---

## Next Steps

After implementing authentication, you can:

1. Implement order management features:
   - Get order book: `GET /api/orders?symbol=BTC`
   - Create orders: `POST /api/orders`
   - Cancel orders: `POST /api/orders/{id}/cancel`

2. Add real-time updates using WebSockets or polling

3. Implement transaction history display

4. Add asset management features

For more details on trading endpoints, see [API_DOCUMENTATION.md](API_DOCUMENTATION.md)
