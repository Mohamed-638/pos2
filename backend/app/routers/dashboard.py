from fastapi import APIRouter, Depends
from sqlalchemy.orm import Session

from .. import crud, schemas
from ..database import get_db

router = APIRouter(prefix="/dashboard", tags=["dashboard"])


@router.get("/branch-sales", response_model=list[schemas.BranchSalesSummary])
def branch_sales_summary(db: Session = Depends(get_db)):
    return crud.branch_sales_summary(db)
