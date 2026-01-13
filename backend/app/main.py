from fastapi import FastAPI

from .database import Base, engine
from .routers import branches, dashboard, products, sales, users

Base.metadata.create_all(bind=engine)

app = FastAPI(
    title="Multi-Branch POS",
    description="Backend API for a multi-branch POS system.",
    version="0.1.0",
)

app.include_router(branches.router)
app.include_router(products.router)
app.include_router(users.router)
app.include_router(sales.router)
app.include_router(dashboard.router)


@app.get("/")
def root():
    return {"status": "ok", "currency": "SDG"}
