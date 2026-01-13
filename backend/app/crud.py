from sqlalchemy import func
from sqlalchemy.orm import Session

from . import models, schemas


def create_branch(db: Session, branch: schemas.BranchCreate) -> models.Branch:
    db_branch = models.Branch(
        name=branch.name,
        address=branch.address,
        phone=branch.phone,
        delivery_enabled=branch.delivery_enabled,
        currency=branch.currency,
    )
    for option in branch.delivery_options:
        db_branch.delivery_options.append(
            models.DeliveryOption(
                zone=option.zone,
                fee=option.fee,
                schedule=option.schedule,
            )
        )
    db.add(db_branch)
    db.commit()
    db.refresh(db_branch)
    return db_branch


def list_branches(db: Session) -> list[models.Branch]:
    return db.query(models.Branch).order_by(models.Branch.id).all()


def update_branch(
    db: Session, branch_id: int, update: schemas.BranchUpdate
) -> models.Branch | None:
    db_branch = db.query(models.Branch).filter(models.Branch.id == branch_id).first()
    if not db_branch:
        return None
    for field, value in update.model_dump(exclude_unset=True).items():
        setattr(db_branch, field, value)
    db.commit()
    db.refresh(db_branch)
    return db_branch


def create_product(db: Session, product: schemas.ProductCreate) -> models.Product:
    db_product = models.Product(
        branch_id=product.branch_id,
        name=product.name,
        sku=product.sku,
        barcode=product.barcode,
        price=product.price,
        is_active=product.is_active,
    )
    db.add(db_product)
    db.commit()
    db.refresh(db_product)
    return db_product


def list_products(db: Session, branch_id: int | None = None) -> list[models.Product]:
    query = db.query(models.Product)
    if branch_id:
        query = query.filter(models.Product.branch_id == branch_id)
    return query.order_by(models.Product.id).all()


def create_user(db: Session, user: schemas.UserCreate) -> models.User:
    db_user = models.User(
        branch_id=user.branch_id,
        username=user.username,
        full_name=user.full_name,
        role=user.role,
        is_active=user.is_active,
    )
    db.add(db_user)
    db.commit()
    db.refresh(db_user)
    return db_user


def list_users(db: Session, branch_id: int | None = None) -> list[models.User]:
    query = db.query(models.User)
    if branch_id:
        query = query.filter(models.User.branch_id == branch_id)
    return query.order_by(models.User.id).all()


def create_sale(db: Session, sale: schemas.SaleCreate) -> models.Sale:
    total_amount = 0
    db_sale = models.Sale(
        branch_id=sale.branch_id,
        cashier_id=sale.cashier_id,
        currency=sale.currency,
        notes=sale.notes,
        total_amount=0,
    )
    for item in sale.items:
        line_total = item.quantity * item.unit_price
        total_amount += line_total
        db_sale.items.append(
            models.SaleItem(
                product_id=item.product_id,
                quantity=item.quantity,
                unit_price=item.unit_price,
                line_total=line_total,
            )
        )
    db_sale.total_amount = total_amount
    db.add(db_sale)
    db.commit()
    db.refresh(db_sale)
    return db_sale


def list_sales(db: Session, branch_id: int | None = None) -> list[models.Sale]:
    query = db.query(models.Sale)
    if branch_id:
        query = query.filter(models.Sale.branch_id == branch_id)
    return query.order_by(models.Sale.created_at.desc()).all()


def branch_sales_summary(db: Session) -> list[schemas.BranchSalesSummary]:
    rows = (
        db.query(
            models.Branch.id,
            models.Branch.name,
            func.coalesce(func.sum(models.Sale.total_amount), 0),
            func.count(models.Sale.id),
        )
        .outerjoin(models.Sale, models.Branch.id == models.Sale.branch_id)
        .group_by(models.Branch.id)
        .order_by(models.Branch.id)
        .all()
    )
    return [
        schemas.BranchSalesSummary(
            branch_id=row[0],
            branch_name=row[1],
            total_sales=float(row[2] or 0),
            transactions=row[3],
        )
        for row in rows
    ]
