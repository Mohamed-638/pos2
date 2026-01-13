# Backend API (POS)

## التشغيل السريع
```bash
python -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
uvicorn app.main:app --reload
```

سيعمل الخادم على `http://127.0.0.1:8000`.

## نقاط النهاية الأساسية
- `POST /branches` إنشاء فرع.
- `GET /branches` عرض الفروع.
- `POST /products` إضافة منتج.
- `POST /users` إضافة مستخدم.
- `POST /sales` تسجيل عملية بيع.
- `GET /dashboard/branch-sales` ملخص المبيعات حسب الفروع.
