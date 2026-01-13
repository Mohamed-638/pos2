from fastapi import APIRouter, Depends, Query, status
from sqlalchemy.orm import Session

from .. import crud, schemas
from ..database import get_db

router = APIRouter(prefix="/users", tags=["users"])


@router.post("", response_model=schemas.User, status_code=status.HTTP_201_CREATED)
def create_user(payload: schemas.UserCreate, db: Session = Depends(get_db)):
    return crud.create_user(db, payload)


@router.get("", response_model=list[schemas.User])
def list_users(
    branch_id: int | None = Query(default=None),
    db: Session = Depends(get_db),
):
    return crud.list_users(db, branch_id=branch_id)
