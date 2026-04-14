const fs = require('fs');
const path = require('path');

const outputPath = path.join(__dirname, '..', '..', 'output', 'pdf', 'verifyhomes-app-summary.pdf');

const page = {
  width: 612,
  height: 792,
  margin: 42,
};

const colors = {
  ink: '0.13 0.16 0.19',
  muted: '0.35 0.40 0.45',
  accent: '0.07 0.47 0.33',
  accentSoft: '0.87 0.94 0.91',
  line: '0.86 0.89 0.92',
};

function pdfEscape(text) {
  return text.replace(/\\/g, '\\\\').replace(/\(/g, '\\(').replace(/\)/g, '\\)');
}

function wrapText(text, fontSize, maxWidth) {
  const avgWidth = fontSize * 0.52;
  const maxChars = Math.max(12, Math.floor(maxWidth / avgWidth));
  const words = text.split(/\s+/);
  const lines = [];
  let current = '';

  for (const word of words) {
    const next = current ? `${current} ${word}` : word;
    if (next.length <= maxChars) {
      current = next;
    } else {
      if (current) lines.push(current);
      current = word;
    }
  }

  if (current) lines.push(current);
  return lines;
}

class PdfBuilder {
  constructor() {
    this.commands = [];
  }

  push(line) {
    this.commands.push(line);
  }

  fillColor(rgb) {
    this.push(`${rgb} rg`);
  }

  strokeColor(rgb) {
    this.push(`${rgb} RG`);
  }

  lineWidth(width) {
    this.push(`${width} w`);
  }

  rect(x, y, width, height, fill = false) {
    this.push(`${x.toFixed(2)} ${y.toFixed(2)} ${width.toFixed(2)} ${height.toFixed(2)} re ${fill ? 'f' : 'S'}`);
  }

  line(x1, y1, x2, y2) {
    this.push(`${x1.toFixed(2)} ${y1.toFixed(2)} m ${x2.toFixed(2)} ${y2.toFixed(2)} l S`);
  }

  text(text, x, y, font = 'F1', size = 10) {
    this.push(`BT /${font} ${size} Tf 1 0 0 1 ${x.toFixed(2)} ${y.toFixed(2)} Tm (${pdfEscape(text)}) Tj ET`);
  }
}

function drawWrappedText(pdf, text, x, y, width, opts = {}) {
  const font = opts.font || 'F1';
  const size = opts.size || 10;
  const leading = opts.leading || size + 3;
  const color = opts.color || colors.ink;
  const lines = wrapText(text, size, width);

  pdf.fillColor(color);
  lines.forEach((line, index) => {
    pdf.text(line, x, y - (index * leading), font, size);
  });

  return y - (lines.length * leading);
}

function drawBulletList(pdf, items, x, y, width, opts = {}) {
  const font = opts.font || 'F1';
  const size = opts.size || 9;
  const leading = opts.leading || size + 3;
  const bulletGap = 10;
  let cursor = y;

  pdf.fillColor(colors.ink);

  for (const item of items) {
    const bulletX = x;
    const textX = x + bulletGap;
    const lines = wrapText(item, size, width - bulletGap);

    pdf.text('-', bulletX, cursor, font, size);
    lines.forEach((line, index) => {
      pdf.text(line, textX, cursor - (index * leading), font, size);
    });

    cursor -= (lines.length * leading) + 2;
  }

  return cursor;
}

function drawSection(pdf, title, x, y, width, renderer, cardHeight) {
  pdf.fillColor('1 1 1');
  pdf.strokeColor(colors.line);
  pdf.lineWidth(1);
  pdf.rect(x, y - cardHeight, width, cardHeight, true);
  pdf.rect(x, y - cardHeight, width, cardHeight, false);

  pdf.fillColor(colors.accentSoft);
  pdf.rect(x, y - 26, width, 26, true);

  pdf.fillColor(colors.accent);
  pdf.text(title, x + 12, y - 17, 'F2', 10);

  return renderer(x + 12, y - 42, width - 24);
}

const pdf = new PdfBuilder();

pdf.fillColor('0.98 0.99 1');
pdf.rect(0, 0, page.width, page.height, true);

pdf.fillColor(colors.accentSoft);
pdf.rect(page.margin, page.height - 110, page.width - (page.margin * 2), 62, true);

pdf.fillColor(colors.accent);
pdf.text('VerifyHomes', page.margin + 18, page.height - 78, 'F2', 22);
pdf.fillColor(colors.ink);
pdf.text('App Summary', page.margin + 145, page.height - 78, 'F1', 18);

drawWrappedText(
  pdf,
  'One-page repo-backed overview generated from Laravel routes, models, migrations, views, and config.',
  page.margin + 18,
  page.height - 98,
  500,
  { size: 9, color: colors.muted }
);

const leftX = page.margin;
const colGap = 18;
const colWidth = (page.width - (page.margin * 2) - colGap) / 2;
const rightX = leftX + colWidth + colGap;
let leftTop = page.height - 132;
let rightTop = page.height - 132;

leftTop = drawSection(pdf, 'What It Is', leftX, leftTop, colWidth, (x, y, width) => {
  return drawWrappedText(
    pdf,
    'VerifyHomes is a Laravel web app for safer landlord-to-tenant rentals, starting with Akure, Ondo State. The repo shows role-based experiences for tenants, landlords, and admin/staff around verified listings and inspection-first rental workflows.',
    x,
    y,
    width,
    { size: 9, leading: 12 }
  );
}, 88) - 12;

leftTop = drawSection(pdf, 'Who It Is For', leftX, leftTop, colWidth, (x, y, width) => {
  return drawWrappedText(
    pdf,
    'Primary persona: tenants/renters looking for safer rentals in Akure. Secondary in-repo users: landlords listing properties and admin/staff users handling verification and operations.',
    x,
    y,
    width,
    { size: 9, leading: 12 }
  );
}, 78) - 12;

leftTop = drawSection(pdf, 'What It Does', leftX, leftTop, colWidth, (x, y, width) => {
  return drawBulletList(pdf, [
    'Public landing page positions the app around verified listings, inspection first, and safer payments.',
    'Authentication supports registration, login, password reset, email verification, and profile management.',
    'Role-based routing gates dedicated dashboards for admin/staff, landlord, tenant, and a generic fallback dashboard.',
    'Users can carry landlord or tenant profile records linked one-to-one with the main user account.',
    'Properties store listing details, rent/caution/service charges, room counts, and Akure/Ondo-focused address fields.',
    'Property records track verification, publication, reviewer, and physical-verification timestamps.',
    'Property images and property documents support cover images, sorted media, and document review status.',
  ], x, y, width, { size: 8.8, leading: 11.2 });
}, 236) - 12;

rightTop = drawSection(pdf, 'How It Works', rightX, rightTop, colWidth, (x, y, width) => {
  return drawBulletList(pdf, [
    'HTTP requests enter Laravel via routes in web.php and auth.php, then flow to Blade views through lightweight controllers.',
    'EnsureRole middleware plus Spatie permission roles enforce access to admin/staff, landlord, and tenant dashboard areas.',
    'Eloquent models map user, landlord_profile, tenant_profile, property, property_image, and property_document tables.',
    'Data persists to the configured database; the default repo config points to SQLite, with MySQL/MariaDB/Postgres/SQL Server options also defined.',
    'Uploaded file storage is wired for local/private and public disks; S3 config exists, but active cloud usage is Not found in repo.',
    'Frontend assets are compiled with Vite, Tailwind CSS, and Alpine.js; API endpoints or separate frontend services are Not found in repo.',
  ], x, y, width, { size: 8.8, leading: 11.2 });
}, 196) - 12;

rightTop = drawSection(pdf, 'How To Run', rightX, rightTop, colWidth, (x, y, width) => {
  return drawBulletList(pdf, [
    'From the repo root, run composer install and npm install if dependencies are not already present.',
    'Create .env from .env.example, then run php artisan key:generate.',
    'Run php artisan migrate --seed to create tables, roles, and the seeded admin account.',
    'Start local development with composer run dev, then open the app at the APP_URL value.',
    'Seeded admin login from repo seeder: admin@verifyhomes.test / password123.',
  ], x, y, width, { size: 8.8, leading: 11.2 });
}, 146) - 12;

rightTop = drawSection(pdf, 'Notes', rightX, rightTop, colWidth, (x, y, width) => {
  return drawBulletList(pdf, [
    'Role self-selection during registration is Not found in repo.',
    'Listing submission, search, favorites, and payment-processing implementation are referenced in copy but not present as completed features in this codebase.',
  ], x, y, width, { size: 8.6, leading: 10.8 });
}, 90);

pdf.strokeColor(colors.line);
pdf.lineWidth(1);
pdf.line(page.margin, 34, page.width - page.margin, 34);
drawWrappedText(
  pdf,
  'Evidence sources: routes, migrations, models, seeded roles/admin, Blade dashboards, and environment/config files in this repository.',
  page.margin,
  22,
  page.width - (page.margin * 2),
  { size: 8, color: colors.muted, leading: 10 }
);

const content = pdf.commands.join('\n');
const contentLength = Buffer.byteLength(content, 'utf8');

const objects = [];
objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
objects[2] = '<< /Type /Pages /Count 1 /Kids [3 0 R] >>';
objects[3] = `<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ${page.width} ${page.height}] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>`;
objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
objects[5] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';
objects[6] = `<< /Length ${contentLength} >>\nstream\n${content}\nendstream`;

let pdfBody = '%PDF-1.4\n';
const offsets = [0];

for (let i = 1; i < objects.length; i += 1) {
  offsets[i] = Buffer.byteLength(pdfBody, 'utf8');
  pdfBody += `${i} 0 obj\n${objects[i]}\nendobj\n`;
}

const xrefStart = Buffer.byteLength(pdfBody, 'utf8');
pdfBody += `xref\n0 ${objects.length}\n`;
pdfBody += '0000000000 65535 f \n';

for (let i = 1; i < objects.length; i += 1) {
  pdfBody += `${String(offsets[i]).padStart(10, '0')} 00000 n \n`;
}

pdfBody += `trailer\n<< /Size ${objects.length} /Root 1 0 R >>\nstartxref\n${xrefStart}\n%%EOF`;

fs.writeFileSync(outputPath, pdfBody, 'binary');
console.log(outputPath);
