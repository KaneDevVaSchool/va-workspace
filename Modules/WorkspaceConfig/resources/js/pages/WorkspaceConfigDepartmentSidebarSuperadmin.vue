<script setup>
//
// superadmin/workspace-config/departments/:departmentId/sidebar — tab "Menu
// hiển thị" trong hub chi tiết phòng ban. Chỉ xem — bật/tắt menu là việc của
// department_director tại manager.workspace-config.sidebar, super_admin
// không sửa thay. Dữ liệu lấy từ WorkspaceConfigDepartmentDetailHub qua inject.
//
import { computed, inject } from 'vue';
import SidebarMenuConfigPanel from '../components/SidebarMenuConfigPanel.vue';

const hub = inject('workspaceConfigDeptDetailHub', null);
const menus = computed(() => hub?.sidebarMenus?.value ?? []);
const loading = computed(() => hub?.loading?.value ?? false);
</script>

<template>
  <SidebarMenuConfigPanel
    :menus="menus"
    :loading="loading"
    intro-eyebrow="Chỉ xem"
    intro-text="Super admin không bật/tắt hay đổi tên thay trưởng phòng. Tên và trạng thái dưới đây là menu trái hiện tại của phòng ban."
  />
</template>
