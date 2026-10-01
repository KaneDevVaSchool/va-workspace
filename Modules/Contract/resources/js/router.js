export default [
  {
    path: '/manager/contract/suppliers',
    name: 'manager.contract.suppliers.index',
    component: () => import('./pages/SupplierList.vue'),
    meta: {
      requiresAuth: true,
      title: 'Nhà cung cấp',
      requiresAnyPermission: ['contract.view', 'contract.manage_department'],
    },
  },
  {
    path: '/manager/contract/suppliers/create',
    name: 'manager.contract.suppliers.create',
    component: () => import('./pages/SupplierForm.vue'),
    meta: {
      requiresAuth: true,
      title: 'Thêm nhà cung cấp',
      requiresPermission: 'contract.manage_department',
    },
  },
  {
    path: '/manager/contract/suppliers/:id',
    name: 'manager.contract.suppliers.show',
    component: () => import('./pages/SupplierDetail.vue'),
    meta: {
      requiresAuth: true,
      title: 'Chi tiết nhà cung cấp',
      requiresAnyPermission: ['contract.view', 'contract.manage_department'],
    },
  },
  {
    path: '/manager/contract/suppliers/:id/edit',
    name: 'manager.contract.suppliers.edit',
    component: () => import('./pages/SupplierForm.vue'),
    meta: {
      requiresAuth: true,
      title: 'Sửa nhà cung cấp',
      requiresPermission: 'contract.manage_department',
    },
  },
];
