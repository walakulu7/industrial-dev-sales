export const ENDPOINTS = {
    AUTH: {
        LOGIN: '/auth/login',
        REGISTER: '/auth/register',
    },
    INVENTORY: {
        YARN: '/inventory/yarn',
        UPDATE_YARN: '/inventory/yarn/update',
        PRODUCTS: '/inventory/products',
        PRODUCT_STOCK: '/inventory/products/stock',
    },
    SALES: {
        CREATE_INVOICE: '/sales/invoice',
    },
    PRODUCTION: {
        ADD: '/production/add',
        HISTORY: '/production/history',
    },
    FINANCE: {
        ADD_TRANSACTION: '/finance/transaction',
        PNL_REPORT: '/finance/pnl',
    },
    ASSETS: {
        GET_ALL: '/assets',
        ADD: '/assets/add',
        DEPRECIATE: '/assets/depreciate',
    }
};