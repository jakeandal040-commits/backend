import assert from 'node:assert/strict'
const base = process.env.TEST_API_URL || 'http://localhost/lab6/public/api'
let token = '', productId
async function call(path, method = 'GET', body, status = 200, authenticated = true) {
  const response = await fetch(`${base}${path}`, { method, headers: { ...(body ? { 'Content-Type': 'application/json' } : {}), ...(authenticated && token ? { Authorization: `Bearer ${token}` } : {}) }, body: body ? JSON.stringify(body) : undefined })
  const text = await response.text()
  assert.equal(response.status, status, `${method} ${path}: expected ${status}, got ${response.status}: ${text.slice(0, 180)}`)
  return text ? JSON.parse(text) : {}
}
try {
  await call('/health')
  for (const [path, method, body] of [['/products', 'GET'], ['/products', 'POST', {}], ['/products/1', 'PUT', {}], ['/products/1', 'PATCH', {}], ['/products/1', 'DELETE']]) await call(path, method, body, 401, false)
  await call('/auth/login', 'POST', { identity: 'admin', password: 'wrong-password' }, 401, false)
  await call('/auth/register', 'POST', { username: 'x', email: 'invalid', password: 'short' }, 422, false)
  await call('/auth/register', 'POST', { username: 'admin', email: 'admin@stockroom.local', password: 'Stockroom123!' }, 409, false)
  const session = await call('/auth/login', 'POST', { identity: process.env.TEST_USERNAME || 'admin', password: process.env.TEST_PASSWORD || 'Stockroom123!' }, 200, false)
  token = session.access_token
  assert.equal((await call('/auth/me')).user.username, process.env.TEST_USERNAME || 'admin')
  await call('/products', 'POST', { product_name: '', description: '', price: '-1', quantity: '-1' }, 422)
  const input = { product_name: "Test & <product> 'name'", description: 'Unicode: café / ₱', price: '123.45', quantity: 5 }
  const created = await call('/products', 'POST', input, 201)
  productId = created.product.id
  assert.equal(created.product.product_name, input.product_name)
  assert.equal(created.product.description, input.description)
  assert.equal(created.product.price, '123.45')
  assert.ok((await call('/products')).products.some(p => p.id === productId))
  const edited = await call(`/products/${productId}`, 'PUT', { ...input, product_name: 'Updated test product', price: '200.00', quantity: 10 })
  assert.equal(edited.product.quantity, 10)
  const patched = await call(`/products/${productId}`, 'PATCH', { quantity: 7 })
  assert.equal(patched.product.quantity, 7)
  assert.equal(patched.product.product_name, 'Updated test product')
  await call(`/products/${productId}`, 'PATCH', { price: '1.999' }, 422)
  await call(`/products/${productId}`, 'DELETE')
  await call(`/products/${productId}`, 'GET', undefined, 404)
  productId = undefined
  const { tokens: rotated } = await call('/auth/refresh', 'POST', { refresh_token: session.refresh_token }, 200, false)
  token = rotated.access_token
  await call('/auth/refresh', 'POST', { refresh_token: session.refresh_token }, 403, false)
  await call('/auth/me')
  await call('/auth/logout', 'POST', { refresh_token: rotated.refresh_token }, 200, false)
  await call('/auth/refresh', 'POST', { refresh_token: rotated.refresh_token }, 403, false)
  const preflight = await fetch(`${base}/products`, { method: 'OPTIONS', headers: { Origin: 'http://localhost:5173', 'Access-Control-Request-Method': 'POST', 'Access-Control-Request-Headers': 'authorization,content-type' } })
  assert.equal(preflight.status, 204)
  assert.equal(preflight.headers.get('access-control-allow-origin'), 'http://localhost:5173')
  console.log('PASS: authentication, protected CRUD, validation, exact text storage, PUT/PATCH, 404, refresh rotation, logout, and CORS.')
} finally {
  if (productId) await call(`/products/${productId}`, 'DELETE').catch(() => {})
}
