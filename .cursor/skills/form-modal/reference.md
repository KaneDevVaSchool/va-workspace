# Form modal — kỹ thuật

Copy từ modal **Thêm tiêu chí** trong `Modules/Evaluation/resources/js/pages/WorkspaceConfigEvaluation.vue`.

## Khung panel

```css
.dialog {
  position: fixed;
  inset: 0;
  z-index: 300;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-5);
  background: var(--color-sidebar-overlay);
}

.dialog-panel--fill {
  width: min(90rem, calc(100vw - 2.5rem));
  height: calc(100vh - 2.5rem);
  max-height: calc(100vh - 2.5rem);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.dialog-body {
  flex: 1;
  min-width: 0;
  min-height: 0;
}

.dialog-head,
.dialog-tabs,
.dialog-actions {
  flex-shrink: 0;
}
```

## Lưới field

```css
.form--cols {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: var(--space-4);
  align-content: start;
}

.form-field--full {
  grid-column: 1 / -1;
}

.form-field--wide {
  grid-column: span 2;
}

/* Tab Sửa: list trái + form phải */
.dialog-body--edit .form--cols {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

@media (max-width: 768px) {
  .form--cols {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 640px) {
  .form--cols,
  .dialog-body--edit .form--cols {
    grid-template-columns: minmax(0, 1fr);
  }

  .form-field--wide {
    grid-column: 1 / -1;
  }
}
```

Danh sách con (mức điểm, hàng lặp): grid 2–3 cột bên trong field `--full`, không xếp dọc một hàng/dòng trên desktop.
