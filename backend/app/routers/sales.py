from fastapi import APIRouter, Depends, Query, status
from sqlalchemy.orm import Session

from .. import crud, schemas
from ..database import get_db

router = APIRouter(prefix="/sales", tags=["sales"])


@router.post("", response_model=schemas.Sale, status_code=status.HTTP_201_CREATED)
def create_sale(payload: schemas.SaleCreate, db: Session = Depends(get_db)):
    return crud.create_sale(db, payload)


@router.get("", response_model=list[schemas.Sale])
def list_sales(
    branch_id: int | None = Query(default=None),
    db: Session = Depends(get_db),
):
    return crud.list_sales(db, branch_id=branch_id)
