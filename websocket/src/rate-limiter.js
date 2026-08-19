export class FixedWindowRateLimiter {
  constructor({ limit, windowMs, now = Date.now }) {
    this.limit = limit;
    this.windowMs = windowMs;
    this.now = now;
    this.count = 0;
    this.resetAt = 0;
  }

  consume() {
    const current = this.now();
    if (current >= this.resetAt) {
      this.count = 0;
      this.resetAt = current + this.windowMs;
    }
    this.count += 1;
    return this.count <= this.limit;
  }

  retryAfterMs() {
    return Math.max(0, this.resetAt - this.now());
  }
}
