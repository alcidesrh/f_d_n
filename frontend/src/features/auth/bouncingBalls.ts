/**
 * Física del fondo del login: elementos circulares que rebotan entre sí, con
 * los bordes de la ventana y con un obstáculo (la tarjeta de login). Se anima
 * con `requestAnimationFrame` escribiendo `left/top/transform` en el DOM.
 */

const FRICTION = 0.991;
const WALL_RESTITUTION = 0.75;
const BALL_RESTITUTION = 0.92;
const IMPULSE_SPEED = [12, 24] as const;
const GRID = { cols: 4, rows: 3 };
const SIZE = 150;

interface Ball {
  x: number;
  y: number;
  vx: number;
  vy: number;
  radius: number;
  rotation: number;
  rotationSpeed: number;
  el: HTMLElement;
}

const random = (min: number, max: number) => Math.random() * (max - min) + min;

function shuffle<T>(array: T[]): T[] {
  for (let i = array.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [array[i], array[j]] = [array[j]!, array[i]!];
  }
  return array;
}

/** Punto al azar dentro de una celda distinta de la grilla para cada elemento. */
function scatteredPoints(count: number) {
  const cells = shuffle(
    Array.from({ length: GRID.cols * GRID.rows }, (_, i) => ({ x: i % GRID.cols, y: Math.floor(i / GRID.cols) })),
  );
  const [W, H] = [window.innerWidth, window.innerHeight];
  return Array.from({ length: count }, (_, i) => {
    const cell = cells[i % cells.length]!;
    return { x: (cell.x + Math.random()) * (W / GRID.cols), y: (cell.y + Math.random()) * (H / GRID.rows) };
  });
}

/** Choque elástico entre dos círculos de igual masa. */
function collideBalls(a: Ball, b: Ball) {
  const dx = b.x - a.x;
  const dy = b.y - a.y;
  const dist = Math.hypot(dx, dy);
  const minDist = a.radius + b.radius;
  if (dist >= minDist || dist === 0) return;
  const nx = dx / dist;
  const ny = dy / dist;
  const overlap = (minDist - dist) / 2;
  a.x -= nx * overlap;
  a.y -= ny * overlap;
  b.x += nx * overlap;
  b.y += ny * overlap;
  const dvx = a.vx - b.vx;
  const dvy = a.vy - b.vy;
  const dvDotN = dvx * nx + dvy * ny;
  if (dvDotN <= 0) return;
  const impulse = dvDotN * BALL_RESTITUTION;
  a.vx -= impulse * nx;
  a.vy -= impulse * ny;
  b.vx += impulse * nx;
  b.vy += impulse * ny;
  const tangentSpeed = dvx * -ny + dvy * nx;
  a.rotationSpeed += tangentSpeed * 0.06;
  b.rotationSpeed -= tangentSpeed * 0.06;
}

/** Choque de un círculo contra un rectángulo (el obstáculo). */
function collideRect(ball: Ball, rect: DOMRect) {
  const dx = ball.x - Math.max(rect.left, Math.min(ball.x, rect.right));
  const dy = ball.y - Math.max(rect.top, Math.min(ball.y, rect.bottom));
  const distSq = dx * dx + dy * dy;
  if (distSq >= ball.radius * ball.radius || distSq === 0) return;
  const dist = Math.sqrt(distSq);
  const nx = dx / dist;
  const ny = dy / dist;
  ball.x += nx * (ball.radius - dist);
  ball.y += ny * (ball.radius - dist);
  const vDotN = ball.vx * nx + ball.vy * ny;
  if (vDotN < 0) {
    ball.vx -= (1 + WALL_RESTITUTION) * vDotN * nx;
    ball.vy -= (1 + WALL_RESTITUTION) * vDotN * ny;
  }
  ball.rotationSpeed += (ball.vy * nx - ball.vx * ny) * 0.03;
}

/** Rebote contra los bordes de la ventana. */
function collideWalls(ball: Ball, W: number, H: number) {
  if (ball.x - ball.radius < 0) {
    ball.x = ball.radius;
    ball.vx = Math.abs(ball.vx) * WALL_RESTITUTION;
    ball.rotationSpeed += ball.vy * 0.02;
  } else if (ball.x + ball.radius > W) {
    ball.x = W - ball.radius;
    ball.vx = -Math.abs(ball.vx) * WALL_RESTITUTION;
    ball.rotationSpeed -= ball.vy * 0.02;
  }
  if (ball.y - ball.radius < 0) {
    ball.y = ball.radius;
    ball.vy = Math.abs(ball.vy) * WALL_RESTITUTION;
    ball.rotationSpeed -= ball.vx * 0.02;
  } else if (ball.y + ball.radius > H) {
    ball.y = H - ball.radius;
    ball.vy = -Math.abs(ball.vy) * WALL_RESTITUTION;
    ball.rotationSpeed += ball.vx * 0.02;
  }
}

function render(ball: Ball) {
  ball.el.style.left = `${ball.x}px`;
  ball.el.style.top = `${ball.y}px`;
  ball.el.style.transform = `translate(-50%, -50%) rotate(${ball.rotation}deg)`;
}

/**
 * Coloca `elements` repartidos por la ventana y arranca la simulación.
 * `obstacle` devuelve el rectángulo contra el que también rebotan.
 */
export function createBouncingBalls(elements: HTMLElement[], obstacle: () => DOMRect | undefined) {
  let frame: number | null = null;
  const balls: Ball[] = scatteredPoints(elements.length).map(({ x, y }, i) => {
    const el = elements[i]!;
    el.style.width = el.style.height = `${SIZE}px`;
    const ball = { x, y, vx: 0, vy: 0, radius: SIZE / 2, rotation: random(-25, 25), rotationSpeed: 0, el };
    render(ball);
    return ball;
  });

  function step(now: number, last: number) {
    const dt = Math.min((now - last) / 16.667, 3); // ~60 fps; tope al volver de otra pestaña
    const [W, H] = [window.innerWidth, window.innerHeight];
    const rect = obstacle();
    const friction = Math.pow(FRICTION, dt);
    for (const ball of balls) {
      ball.vx *= friction;
      ball.vy *= friction;
      ball.rotationSpeed *= friction;
      ball.x += ball.vx * dt;
      ball.y += ball.vy * dt;
      ball.rotation += ball.rotationSpeed * dt;
      collideWalls(ball, W, H);
      if (rect) collideRect(ball, rect);
    }
    for (let i = 0; i < balls.length; i++) {
      for (let j = i + 1; j < balls.length; j++) collideBalls(balls[i]!, balls[j]!);
    }
    balls.forEach(render);
    frame = requestAnimationFrame((next) => step(next, now));
  }

  return {
    start() {
      frame = requestAnimationFrame((now) => step(now, now));
    },
    stop() {
      if (frame !== null) cancelAnimationFrame(frame);
    },
    /** Impulsa cada elemento hacia un punto al azar, con giro aleatorio. */
    scatter() {
      const targets = scatteredPoints(balls.length);
      balls.forEach((ball, i) => {
        const dx = targets[i]!.x - ball.x;
        const dy = targets[i]!.y - ball.y;
        const dist = Math.hypot(dx, dy);
        if (dist > 0) {
          const speed = random(...IMPULSE_SPEED);
          ball.vx = (dx / dist) * speed;
          ball.vy = (dy / dist) * speed;
        }
        ball.rotationSpeed = random(-4, 4);
      });
    },
  };
}
