# نشر المشروع مجانًا (Backend + Frontend)

الخطة: Backend (Laravel) على **Render.com** (Free Web Service عبر Docker)، و Frontend (HTML/CSS/JS) على **Cloudflare Pages**. الاتنين مجانين تمامًا وملهمش كارت ائتمان.

الملفات التالية اتضافت للمشروع عشان دي تشتغل:
- `backend/Dockerfile`
- `backend/docker-entrypoint.sh`
- `backend/.dockerignore`
- تعديل بسيط في `frontend/js/api.js` عشان يفرق بين local وproduction تلقائيًا حسب الـ domain.

## قيود الخطة المجانية (مهم تعرفها)

- **Render Free**: الـ service بينام بعد 15 دقيقة بدون طلبات، وأول طلب بعدها بياخد 30-50 ثانية يصحى. طبيعي في الخطة المجانية.
- **البيانات (SQLite + الصور المرفوعة)**: بتتصفر مع كل **deploy جديد** (يعني كل مرة تعمل push للـ backend). أثناء تشغيل الـ service عاديًا (نوم/صحيان) البيانات بتفضل موجودة، بس أي push جديد للكود بيعمل build جديد وبيمسحها. ده مقبول لمشروع تجريبي/portfolio. لو حبيت لاحقًا ثبات حقيقي للبيانات، الحل إنك تحول الـ DB لقاعدة بيانات خارجية مجانية زي Neon أو Supabase (Postgres) — ده خطوة إضافية ممكن نعملها بعدين لو احتجتها.
- **الكتالوج (350 منتج)**: `docker-entrypoint.sh` بيتأكد أول ما الـ container يشتغل — لو جدول `products` فاضي، بيشغّل `RealCatalogSeeder` تلقائيًا. يعني كل deploy جديد بيرجع الكتالوج تلقائي من غير ما تعمل حاجة يدوي، بس ده بيمسح ويعيد بناء المنتجات والبراندات من الصفر كل مرة (طبيعي لأن الداتا أصلًا بتتصفر مع كل deploy زي ما فوق).

## الخطوة 1: ادفع التعديلات دي على GitHub

لازم أول حاجة إني أعمل commit و push للتعديلات (Dockerfile وغيره) على الريبو `Osama2214/pc-builder` قبل ما تقدر توصل Render و Cloudflare بيه. هطلب تأكيدك على ده في الشات.

## الخطوة 2: نشر الـ Backend على Render

1. روح على https://render.com وسجل دخول بحساب GitHub بتاعك (مجاني، من غير كارت).
2. من الـ Dashboard: **New +** → **Web Service**.
3. اختار **Connect a repository** وحدد `Osama2214/pc-builder`.
4. في الإعدادات:
   - **Name**: `pc-builder-api` (لو الاسم ده مش متاح، اختار اسم تاني وعدّل السطر في `frontend/js/api.js` بعدين ليطابق الاسم).
   - **Root Directory**: `backend`
   - **Runtime**: `Docker`
   - **Instance Type**: `Free`
   - **Health Check Path**: `/up`
5. في تبويب **Environment**، استخدم زرار "Add from .env" (أو ضيفهم واحد واحد) والصق البلوك ده:

```
APP_NAME=PC Builder
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:TruclTIVSvf2YvkxAeBwpgQC0X+f3RdudAxR7330e8I=
APP_URL=https://pc-builder-api.onrender.com

APP_LOCALE=en
APP_FALLBACK_LOCALE=en

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=sqlite

SESSION_DRIVER=database
SESSION_LIFETIME=120

FILESYSTEM_DISK=public
QUEUE_CONNECTION=database
CACHE_STORE=database
```

   - غيّر `APP_URL` لو سميت الـ service باسم مختلف (لازم يطابق الـ URL اللي Render هيديهولك بالظبط، بـ https).
   - كمان ضيف مفاتيح الـ AI بتاعتك من `backend/.env` المحلي (متنساش تنسخهم من عندك، مش هحطهم هنا لأنهم أسرار):
     - `GEMINI_API_KEY`
     - `GROQ_API_KEY`
     - `OPENROUTER_API_KEY`
     - `OPENROUTER_MODEL` (القيمة: `deepseek/deepseek-chat`)
6. اضغط **Create Web Service**. أول build بياخد كذا دقيقة (composer install جوه الـ Docker image).
7. لما يخلص، هيديك URL زي `https://pc-builder-api.onrender.com` — جرب تفتح `<URL>/up` وتتأكد إنه بيرجع استجابة سليمة.

## الخطوة 3: نشر الـ Frontend على Cloudflare Pages

1. روح على https://pages.cloudflare.com وسجل دخول (مجاني).
2. **Create application** → **Pages** → **Connect to Git** → اختار الريبو `Osama2214/pc-builder`.
3. الإعدادات:
   - **Framework preset**: `None`
   - **Build command**: (سيبه فاضي)
   - **Build output directory**: `frontend`
4. اضغط **Save and Deploy**.
5. هيديك رابط زي `https://pc-builder.pages.dev` — ده الموقع بتاعك الشغال.

## الخطوة 4: تأكد إن كل حاجة متوصلة

- افتح رابط الـ Cloudflare Pages، جرب تسجل حساب/تسجل دخول، وشوف إن المنتجات بتظهر (ده معناه إنه بينادي الـ API بتاع Render صح).
- لو الصور مش ظاهرة: يبقى غالبًا `APP_URL` في Render مش مظبوط صح، أو الـ storage link مش اتعمل — جرب تعمل **Manual Deploy** تاني بعد التأكد من الـ env vars.

---

بعد كده لو حبيت domain مخصص (اسم دومين بتاعك)، الاتنين (Render و Cloudflare Pages) بيدعموا custom domains مجانًا برضو.
