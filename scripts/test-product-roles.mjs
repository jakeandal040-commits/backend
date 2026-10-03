import assert from 'node:assert/strict'
const base = process.env.TEST_API_URL || 'http://localhost/lab6/public/api'
const password = process.env.TEST_ROLE_PASSWORD
assert.ok(password, 'Run this test through php scripts/test-product-roles.php')
async function call(path, method = 'GET', body, token, status = 200) {
  const response = await fetch(`${base}${path}`, { method, headers: { ...(body ? { 'Content-Type': 'application/json' } : {}), ...(token ? { Authorization: `Bearer ${token}` } : {}) }, body: body ? JSON.stringify(body) : undefined })
  const text = await response.text()
  assert.equal(response.status, status, `${method} ${path}: ${text.slice(0, 160)}`)
  return text ? JSON.parse(text) : {}
}
const admin = await call('/auth/login', 'POST', { identity: process.env.TEST_ROLE_ADMIN, password })
const viewer = await call('/auth/login', 'POST', { identity: process.env.TEST_ROLE_USER, password })
let id
try {
  const original = { product_name: 'Role access test', description: 'Disposable test product', price: '12.34', quantity: 3 }
  const { product } = await call('/products', 'POST', original, admin.access_token, 201)
  id = product.id
  const list = await call('/products', 'GET', undefined, viewer.access_token)
  assert.ok(list.products.some(p => p.id === id))
  assert.equal((await call(`/products/${id}`, 'GET', undefined, viewer.access_token)).product.product_name, original.product_name)
  for (const [path, method, body] of [
    ['/products', 'POST', { ...original, role: 'admin' }],
    [`/products/${id}`, 'PUT', { ...original, product_name: 'Unauthorized edit' }],
    [`/products/${id}`, 'PATCH', { quantity: 0, role: 'admin' }],
    [`/products/${id}`, 'DELETE'],
  ]) await call(path, method, body, viewer.access_token, 403)
  const unchanged = (await call(`/products/${id}`, 'GET', undefined, admin.access_token)).product
  assert.equal(unchanged.quantity, original.quantity)
  assert.equal(unchanged.product_name, original.product_name)
  await call(`/products/${id}`, 'PUT', { ...original, price: '22.00', quantity: 8 }, admin.access_token)
  assert.equal((await call(`/products/${id}`, 'PATCH', { quantity: 9 }, admin.access_token)).product.quantity, 9)
  // Protected fields are ignored when inserting/updating a product.
  const before = (await call(`/products/${id}`, 'GET', undefined, admin.access_token)).product
  const after = (await call(`/products/${id}`, 'PATCH', { id: id + 1000, created_at: '2000-01-01 00:00:00', quantity: 10 }, admin.access_token)).product
  assert.equal(after.id, id)
  assert.equal(after.created_at, before.created_at)
  await call('/auth/register', 'POST', { username: process.env.TEST_ROLE_REGISTER, email: `${process.env.TEST_ROLE_REGISTER}@test.local`, password, role: 'admin' }, undefined, 201)
  const registered = await call('/auth/login', 'POST', { identity: process.env.TEST_ROLE_REGISTER, password })
  assert.equal(registered.user.role, 'user')
  await call('/products', 'POST', original, registered.access_token, 403)
  const parts = viewer.access_token.split('.')
  const claims = JSON.parse(Buffer.from(parts[1], 'base64url').toString())
  parts[1] = Buffer.from(JSON.stringify({ ...claims, role: 'admin' })).toString('base64url')
  await call('/products', 'POST', original, parts.join('.'), 401)
  await call(`/products/${id}`, 'DELETE', undefined, admin.access_token)
  await call(`/products/${id}`, 'GET', undefined, viewer.access_token, 404)
  id = undefined
  console.log('PASS: admin CRUD; user list/detail only; all user writes denied; registration cannot select admin; forged roles rejected; protected model fields preserved.')
} finally {
  if (id) await call(`/products/${id}`, 'DELETE', undefined, admin.access_token).catch(() => {})
}
