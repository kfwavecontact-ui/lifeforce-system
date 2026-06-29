<?php

namespace App\Enums;

enum AccountTransactionSourceType: string
{
    /**
     * 授業料・入会金売上（invoice_items）
     */
    case InvoiceItem = 'invoice_item';

    /**
     * ショップ売上
     */
    case ShopOrder = 'shop_order';

    /**
     * イベント売上
     */
    case EventApplication = 'event_application';

    /**
     * スポット売上
     */
    case SpotSale = 'spot_sale';

    /**
     * 返金
     */
    case Refund = 'refund';

    /**
     * ポイント商品交換
     */
    case PointExchange = 'point_exchange';

    /**
     * 経費
     */
    case Expense = 'expense';
}