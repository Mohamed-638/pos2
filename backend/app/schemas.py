from datetime import datetime
from typing import List, Optional

from pydantic import BaseModel, ConfigDict, Field


class DeliveryOptionBase(BaseModel):
    zone: str
    fee: float = 0
    schedule: str = ""


class DeliveryOptionCreate(DeliveryOptionBase):
    pass


class DeliveryOption(DeliveryOptionBase):
    model_config = ConfigDict(from_attributes=True)

    id: int


class BranchBase(BaseModel):
    name: str
    address: str
    phone: str
    delivery_enabled: bool = False
    currency: str = "الجنيه السوداني"


class BranchCreate(BranchBase):
    delivery_options: List[DeliveryOptionCreate] = Field(default_factory=list)


class BranchUpdate(BaseModel):
    name: Optional[str] = None
    address: Optional[str] = None
    phone: Optional[str] = None
    delivery_enabled: Optional[bool] = None
    currency: Optional[str] = None


class Branch(BranchBase):
    model_config = ConfigDict(from_attributes=True)

    id: int
    delivery_options: List[DeliveryOption] = Field(default_factory=list)


class ProductBase(BaseModel):
    name: str
    price: float
    sku: Optional[str] = None
    barcode: Optional[str] = None
    is_active: bool = True


class ProductCreate(ProductBase):
    branch_id: int


class Product(ProductBase):
    model_config = ConfigDict(from_attributes=True)

    id: int
    branch_id: int


class UserBase(BaseModel):
    username: str
    full_name: str
    role: str
    is_active: bool = True


class UserCreate(UserBase):
    branch_id: int


class User(UserBase):
    model_config = ConfigDict(from_attributes=True)

    id: int
    branch_id: int


class SaleItemBase(BaseModel):
    product_id: int
    quantity: int = 1
    unit_price: float


class SaleItemCreate(SaleItemBase):
    pass


class SaleItem(SaleItemBase):
    model_config = ConfigDict(from_attributes=True)

    id: int
    line_total: float


class SaleCreate(BaseModel):
    branch_id: int
    cashier_id: int
    currency: str = "SDG"
    notes: Optional[str] = None
    items: List[SaleItemCreate]


class Sale(BaseModel):
    model_config = ConfigDict(from_attributes=True)

    id: int
    branch_id: int
    cashier_id: int
    total_amount: float
    currency: str
    created_at: datetime
    notes: Optional[str] = None
    items: List[SaleItem]


class BranchSalesSummary(BaseModel):
    branch_id: int
    branch_name: str
    total_sales: float
    transactions: int
