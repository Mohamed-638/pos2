from fastapi import APIRouter, Depends, Query, status
from sqlalchemy.orm import Session

from .. import crud, schemas
from ..database import get_db

router = APIRouter(prefix="/products", tags=["products"])


@router.post("", response_model=schemas.Product, status_code=status.HTTP_201_CREATED)
def create_product(payload: schemas.ProductCreate, db: Session = Depends(get_db)):
    return crud.create_product(db, payload)


@router.get("", response_model=list[schemas.Product])
def list_products(
    branch_id: int | None = Query(default=None),
    db: Session = Depends(get_db),
):
    return crud.list_products(db, branch_id=branch_id)
