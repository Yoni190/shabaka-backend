import http from 'k6/http';
import { check, sleep } from 'k6';

// 1. Define the traffic stages and failure thresholds
export const options = {
  stages: [
    { duration: '1m', target: 3 },   // Warm up (3 users)
    { duration: '3m', target: 10 },  // Target limit for Render Free Tier (10 users)
    { duration: '1m', target: 0 },   // Cool down
  ],
  thresholds: {
    http_req_failed: ['rate<0.05'],   // Fail test if more than 5% of requests return 4xx/5xx errors
    http_req_duration: ['p(95)<1500'], // 95% of requests must respond in under 1.5 seconds (generous for 0.1 CPU)
  },
};

// Change this to your live Render API URL (or local URL for testing)
const BASE_URL = 'https://shabaka-backend.onrender.com/api/v1';

export default function () {
  // --- STEP 1: LOGIN & OBTAIN SANCTUM TOKEN ---
  const loginUrl = `${BASE_URL}/login`;
  const loginPayload = JSON.stringify({
    email: 'john@shabaka.com', // Ensure this user exists in your DB or use a seed user
    password: 'WbvjukLFjX8PABX',
  });

  const loginParams = {
    headers: { 'Content-Type': 'application/json' },
  };

  const loginRes = http.post(loginUrl, loginPayload, loginParams);

  // Validate the login response
  const loginCheck = check(loginRes, {
    'login status is 200': (r) => r.status === 200,
    'has token in response': (r) => r.json('token') !== undefined,
  });

  // If login fails, pause and stop this iteration to avoid cascading errors
  if (!loginCheck) {
    sleep(2);
    return;
  }

  // Extract the token (Assuming your Laravel Controller returns: return response()->json(['token' => \$token]);)
  const token = loginRes.json('token');

  // --- STEP 2: BRAKING PAUSE ---
  // Simulate the user waiting 2-4 seconds after logging in
  sleep(Math.random() * 2 + 2);

  // --- STEP 3: ACCESS PROTECTED LARAVEL ROUTE ---
  // Authenticated headers using Bearer token
  const authParams = {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json',
      'Accept': 'application/json', // Forces Laravel to return JSON errors instead of redirecting to a login page
    },
  };

  // Replace with an actual route protected by the 'auth:sanctum' middleware
  const dashboardRes = http.get(`${BASE_URL}/addresses`, authParams);

  check(dashboardRes, {
    'dashboard status is 200': (r) => r.status === 200,
  });

  // Final think-time pause before the user makes another request or logs out
  sleep(Math.random() * 3 + 2);
}
