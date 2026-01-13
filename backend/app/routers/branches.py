from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.orm import Session

from .. import crud, schemas
from ..database import get_db

router = APIRouter(prefix="/branches", tags=["branches"])


@router.post("", response_model=schemas.Branch, status_code=status.HTTP_201_CREATED)
def create_branch(payload: schemas.BranchCreate, db: Session = Depends(get_db)):
    return crud.create_branch(db, payload)


@router.get("", response_model=list[schemas.Branch])
def list_branches(db: Session = Depends(get_db)):
    return crud.list_branches(db)


@router.patch("/{branch_id}", response_model=schemas.Branch)
def update_branch(
    branch_id: int, payload: schemas.BranchUpdate, db: Session = Depends(get_db)
):
    branch = crud.update_branch(db, branch_id, payload)
    if not branch:
        raise HTTPException(status_code=404, detail="Branch not found")
    return branch
