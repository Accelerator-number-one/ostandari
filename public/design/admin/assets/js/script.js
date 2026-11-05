// ========================================================================
// header menu items
let visibleBox = null;

function closeThisBox(clickButtonId, contentBoxId, highlightClassName) {
    const clickBtn = document.getElementById(clickButtonId);
    const box = document.getElementById(contentBoxId);

    if (box && box.classList.contains('active')) {
        box.classList.remove('active');
        if (clickBtn) {
            clickBtn.classList.remove(highlightClassName);
        }

        if (visibleBox === box) {
            visibleBox = null;
        }
    }
}

function openThisBox(clickButtonId, contentBoxId, highlightClassName) {
    const clickBtn = document.getElementById(clickButtonId);
    const box = document.getElementById(contentBoxId);

    if (visibleBox && visibleBox !== box) {
        if (visibleBox.id === 'hiddendiv1') {
            closeThisBox('clickdiv1', 'hiddendiv1', 'highlight');
        } else if (visibleBox.id === 'hiddendiv2') {
            closeThisBox('clickdiv2', 'hiddendiv2', 'highlight');
        }
        else if (visibleBox.id === 'hiddendiv3') {
            closeThisBox('clickdiv3', 'hiddendiv3', 'highlight');
        }
        else if (visibleBox.id === 'hiddendiv4') {
            closeThisBox('clickdiv4', 'hiddendiv4', 'highlight');
        }

    }


    if (box) {
        const isNowActive = box.classList.toggle('active');
        if (clickBtn) {
            clickBtn.classList.toggle(highlightClassName);
        }

        if (isNowActive) {
            visibleBox = box;
        } else {
            if (visibleBox === box) {
                visibleBox = null;
            }
        }
    }
}

// --- Setup for Instance 1 ---
const click1 = document.getElementById('clickdiv1');
const box1 = document.getElementById('hiddendiv1');
const exit1 = document.querySelector('.exit-btn-1');

if (click1) {
    click1.addEventListener('click', function (event) {
        event.stopPropagation();
        openThisBox('clickdiv1', 'hiddendiv1', 'highlight');
    });
}
if (exit1) {
    exit1.addEventListener('click', function (event) {
        event.stopPropagation();
        closeThisBox('clickdiv1', 'hiddendiv1', 'highlight');
    });
}

document.addEventListener('click', function () {
    if (visibleBox === box1 && !box1.contains(event.target) && !click1.contains(event.target)) {
        closeThisBox('clickdiv1', 'hiddendiv1', 'highlight');
    }
});
if (box1) {
    box1.addEventListener('click', function (event) {
        event.stopPropagation();
    });
}


// --- Setup for Instance 2 ---
const click2 = document.getElementById('clickdiv2');
const box2 = document.getElementById('hiddendiv2');
const exit2 = document.querySelector('.exit-btn-2');

if (click2) {
    click2.addEventListener('click', function (event) {
        event.stopPropagation();
        openThisBox('clickdiv2', 'hiddendiv2', 'highlight');
    });
}
if (exit2) {
    exit2.addEventListener('click', function (event) {
        event.stopPropagation();
        closeThisBox('clickdiv2', 'hiddendiv2', 'highlight');
    });
}
document.addEventListener('click', function () {
    if (visibleBox === box2 && !box2.contains(event.target) && !click2.contains(event.target)) {
        closeThisBox('clickdiv2', 'hiddendiv2', 'highlight');
    }
});
if (box2) {
    box2.addEventListener('click', function (event) {
        event.stopPropagation();
    });
}

// --- Setup for Instance 3 ---
const click3 = document.getElementById('clickdiv3');
const box3 = document.getElementById('hiddendiv3');
const exit3 = document.querySelector('.exit-btn-3');

if (click3) {
    click3.addEventListener('click', function (event) {
        event.stopPropagation();
        openThisBox('clickdiv3', 'hiddendiv3', 'highlight');
    });
}
if (exit3) {
    exit3.addEventListener('click', function (event) {
        event.stopPropagation();
        closeThisBox('clickdiv3', 'hiddendiv3', 'highlight');
    });
}
document.addEventListener('click', function () {
    if (visibleBox === box3 && !box3.contains(event.target) && !click3.contains(event.target)) {
        closeThisBox('clickdiv3', 'hiddendiv3', 'highlight');
    }
});
if (box3) {
    box3.addEventListener('click', function (event) {
        event.stopPropagation();
    });
}






// --- !!! IMPORTANT !!! ---
// For each additional instance (3, 4, 5, 6, 7):
// 1. Copy the entire block of code above (from "Setup for Instance X" to the end of its listeners).
// 2. Change all the numbers (1 to 2, 2 to 3, etc.).
// 3. Update the IDs ('clickdiv3', 'hiddendiv3') and selectors ('.exit-btn-3').
// 4. Add an 'else if' condition in the `openThisBox` function to handle closing the correct previous box.
//    For example, after the 'else if (visibleBox.id === 'hiddendiv2')' block, add:
//    else if (visibleBox.id === 'hiddendiv3') {
//        closeThisBox('clickdiv3', 'hiddendiv3', 'highlight');
//    }
// --- !!! ---



// ========================================================================
// chatbox open header

// const openModalBtn = document.getElementById('openModalBtn');
// const closeModalBtn = document.getElementById('closeModalBtn');
// const modalContent = document.getElementById('modalContent');

// openModalBtn.addEventListener('click', () => {
//     modalContent.classList.add('active');


//     setTimeout(() => {
//         modalContent.classList.add('active');
//     }, 10);
// });

// function closeModal() {
//     modalContent.classList.remove('active');
// }

// ========================================================================
// sidebar responsive js

(function () {
    const toggleBtn = document.getElementById('sidebarHamburgerBtn') || document.querySelector('.hambergur-responsive-btn');
    const closeBtn = document.getElementById('sidebarCloseBtn') || document.querySelector('.sidebar-close-btn');
    const sidebarParent = document.getElementById('sidebarParent') || document.querySelector('.sidebar-parent');
    const darkBg = document.getElementById('sidebarDarkBg') || document.querySelector('.dark-background-tablet');

    if (!toggleBtn || !sidebarParent) return;

    function openSidebar() {
        sidebarParent.classList.add('open');
        if (darkBg) darkBg.classList.add('open');
        toggleBtn.setAttribute('aria-expanded', 'true');
        document.body.classList.add('sidebar-open-lock');
    }

    function closeSidebar() {
        sidebarParent.classList.remove('open');
        if (darkBg) darkBg.classList.remove('open');
        toggleBtn.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('sidebar-open-lock');
    }

    // Open / close with the hamburger button
    toggleBtn.addEventListener('click', () => {
        sidebarParent.classList.contains('open') ? closeSidebar() : openSidebar();
    });

    // Close with the X button inside the drawer
    if (closeBtn) closeBtn.addEventListener('click', closeSidebar);

    // Close by tapping the dark backdrop
    if (darkBg) darkBg.addEventListener('click', closeSidebar);

    // Close with Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeSidebar();
    });

    // Close automatically after picking a menu link (nicer mobile UX)
    sidebarParent.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', closeSidebar);
    });

    // If the window is resized back to desktop width, make sure the drawer resets
    window.addEventListener('resize', () => {
        if (window.innerWidth > 1024) closeSidebar();
    });
})();


// ========================================================================
// extended side menu submenues


document.querySelectorAll('.menu-item').forEach(menuItem => {
    // Check if this menu item has no-submenu class
    if (menuItem.classList.contains('no-submenu')) {
        // Just let the link work normally, no submenu logic
        return;
    }

    const link = menuItem.querySelector('.sidebar-extended-pages-link');
    const submenu = menuItem.querySelector('.submenu-items');
    const chevronIcon = link.querySelector('.extended-category-icon');

    // Skip if no submenu exists
    if (!submenu) return;

    if (submenu.classList.contains('open')) {
        chevronIcon.classList.add('chevron-rotated');
    }

    link.addEventListener('click', function (e) {
        e.preventDefault();

        const isCurrentlyOpen = submenu.classList.contains('open');

        document.querySelectorAll('.menu-item').forEach(otherItem => {
            // Skip items with no-submenu class
            if (otherItem.classList.contains('no-submenu')) return;

            const otherSubmenu = otherItem.querySelector('.submenu-items');
            const otherChevron = otherItem.querySelector('.extended-category-icon');

            if (otherSubmenu && otherSubmenu.classList.contains('open')) {
                otherSubmenu.classList.remove('open');
                if (otherChevron) otherChevron.classList.remove('chevron-rotated');
            }
        });

        if (!isCurrentlyOpen) {
            submenu.classList.add('open');
            chevronIcon.classList.add('chevron-rotated');
        }
    });
});

// ========================================================================
// date picker

if (typeof jalaliDatepicker !== 'undefined' && typeof jalaliDatepicker.startWatch === 'function') {
    jalaliDatepicker.startWatch();
}


// ========================================================================
// select2
$(document).ready(function () {
    $('.mySelect').select2({

        width: 'resolve'
    });
});


// ========================================================================
// Upload file
function setupFileInputListener(fileInputId, fileNameSpanId) {
    const fileInput = document.getElementById(fileInputId);
    const fileNameSpan = document.getElementById(fileNameSpanId);

    if (fileInput && fileNameSpan) {
        fileInput.addEventListener('change', function (e) {
            if (this.files && this.files.length > 0) {
                fileNameSpan.textContent = this.files[0].name;
            } else {
                fileNameSpan.textContent = '';
            }
        });
    }
}


setupFileInputListener('fileInput2', 'fileName2');


setupFileInputListener('editFileInput', 'editFileName');

function openEditModalWithFile(fileName) {
    document.getElementById('editCommitmentModal').classList.add('active');
    const editFileNameSpan = document.getElementById('editFileName');
    if (editFileNameSpan && fileName) {
        editFileNameSpan.textContent = fileName;
    }
}



// ========================================================================
// multi select for search filter

$(document).ready(function () {
    $('.search-field-select-main').select2({
        placeholder: "جستجو براساس ...",
        width: 'resolve',
        templateSelection: function (data, container) {
            return '';
        }
    });

    $('.search-field-select-main').on('select2:select select2:unselect', function () {
        setTimeout(function () {
            $('.search-field-select-main').next('.select2-container').find('.select2-selection__choice').remove();
            $('.search-field-select-main').next('.select2-container').find('.select2-selection__rendered').find('li').hide();
        }, 10);
        updateActiveFilters();
        performSearch();
    });

    $('.search-field-select-country').select2({
        width: 'resolve',
    });

    $('.search-field-select-country').on('change', function () {
        var selectedValue = $(this).val();
        console.log('کشور انتخاب شده:', selectedValue);
    });

    updateActiveFilters();
});

function updateActiveFilters() {
    const selectedValues = $('.search-field-select-main').val() || [];
    const activeFiltersContainer = document.getElementById('activeFilters');

    if (!activeFiltersContainer) return;

    if (selectedValues.length === 0 || selectedValues.includes('all')) {
        activeFiltersContainer.innerHTML = '';
        return;
    }

    const fieldNames = {
        'all': 'همه',
        'univName': 'عنوان دانشگاه',
        'univType': 'نوع دانشگاه',
        'city': 'شهر',
        'address': 'آدرس',
        'postalCode': 'کد پستی',
        'link': 'لینک استعلام'
    };

    let html = '';
    selectedValues.forEach(value => {
        if (value !== 'all') {
            html += `
                    <div class="active-filter-tag">
                        <span>${fieldNames[value] || value}</span>
                        <button onclick="removeFilter('${value}')">×</button>
                    </div>
                `;
        }
    });
    activeFiltersContainer.innerHTML = html;
}

function removeFilter(value) {
    const currentValues = $('.search-field-select-main').val() || [];
    const newValues = currentValues.filter(v => v !== value);

    $('.search-field-select-main').val(newValues).trigger('change');
    updateActiveFilters();
    performSearch();
}

function performSearch() {
    const searchText = document.getElementById('searchInput')?.value.trim().toLowerCase() || '';
    const selectedFields = $('.search-field-select-main').val() || [];
    const rows = document.querySelectorAll('#educationTableBody tr');

    if (rows.length === 0) return;

    rows.forEach(row => {
        let show = false;

        if (searchText === '') {
            show = true;
        } else if (selectedFields.length === 0 || selectedFields.includes('all')) {
            const cells = row.querySelectorAll('td');
            for (let i = 1; i < cells.length - 2; i++) {
                if (cells[i] && cells[i].textContent.trim().toLowerCase().includes(searchText)) {
                    show = true;
                    break;
                }
            }
        } else {
            const cells = row.querySelectorAll('td');
            for (const field of selectedFields) {
                let cellIndex = 1;
                switch (field) {
                    case 'univName': cellIndex = 1; break;
                    case 'univType': cellIndex = 2; break;
                    case 'city': cellIndex = 3; break;
                    case 'address': cellIndex = 4; break;
                    case 'postalCode': cellIndex = 5; break;
                    case 'link': cellIndex = 6; break;
                    default: cellIndex = 1;
                }
                if (cells[cellIndex] && cells[cellIndex].textContent.trim().toLowerCase().includes(searchText)) {
                    show = true;
                    break;
                }
            }
        }
        row.style.display = show ? '' : 'none';
    });
}


// ========================================================================
// remove  search text
document.getElementById('resetSearchBtn')?.addEventListener('click', function () {
    document.getElementById('searchInput').value = '';
    $('.search-field-select-main').val(null).trigger('change');
    $('.search-field-select-country').val(null).trigger('change');
    document.querySelectorAll('#educationTableBody tr').forEach(row => row.style.display = '');
});


// ========================================================================
//  open and close edit modal
function openAddCommitmentModal() {
    document.getElementById('editCommitmentModal').classList.add('active');
    document.getElementById('commitmentTitle').value = '';
    document.getElementById('closeFileCheckbox').checked = false;
}

function closeEditCommitmentModal() {
    document.getElementById('editCommitmentModal').classList.remove('active');
}


// ===== cbn- prefixed behaviours (namespaced to avoid clashing with the project's existing scripts) =====

// place the (position:fixed) dropdown right under its three-dot button,
// measured against the viewport so it is never clipped/cut off by a
// scrolling/overflow-hidden ancestor (e.g. the table wrapper)
function cbnPositionActionsMenu(triggerEl, menu) {
    const triggerRect = triggerEl.getBoundingClientRect();
    const menuRect = menu.getBoundingClientRect();

    // align the menu's right edge with the button's right edge (RTL-friendly),
    // but keep it fully on-screen
    let left = triggerRect.right - menuRect.width;
    left = Math.min(Math.max(left, 8), window.innerWidth - menuRect.width - 8);

    // open below the button, flip above it if there isn't enough room
    let top = triggerRect.bottom + 6;
    if (top + menuRect.height > window.innerHeight - 8) {
        top = triggerRect.top - menuRect.height - 6;
    }

    menu.style.left = left + 'px';
    menu.style.top = top + 'px';
}

function cbnCloseAllActionsMenus() {
    document.querySelectorAll('.cbn-actions-menu.cbn-actions-menu-open').forEach(function (m) {
        m.classList.remove('cbn-actions-menu-open');
    });
}

// toggle the three-dot action menu on a table row
function cbnCloseAllActionsMenus() {
    document.querySelectorAll('.cbn-actions-menu.cbn-actions-menu-open').forEach(function (m) {
        m.classList.remove('cbn-actions-menu-open');
    });
}

function cbnPositionActionsMenu(triggerEl, menu) {
    const triggerRect = triggerEl.getBoundingClientRect();
    const menuRect = menu.getBoundingClientRect();

    // به‌جای پایینِ دکمه، کنارِ دکمه (سمت چپش، چون صفحه RTL هست) باز میشه
    let left = triggerRect.left - menuRect.width - 6;
    let openedOnRight = false;
    if (left < 8) {
        // اگه جا نبود سمت چپ، بچرخون سمت راست دکمه
        left = triggerRect.right + 6;
        openedOnRight = true;
    }
    if (openedOnRight && left + menuRect.width > window.innerWidth - 8) {
        left = window.innerWidth - menuRect.width - 8;
    }

    // عمودی، وسط‌چین با ارتفاع دکمه — و داخل صفحه نگه‌ش می‌داره
    let top = triggerRect.top + (triggerRect.height / 2) - (menuRect.height / 2);
    top = Math.min(Math.max(top, 8), window.innerHeight - menuRect.height - 8);

    menu.style.left = left + 'px';
    menu.style.top = top + 'px';
}

function cbnToggleActionsMenu(triggerEl) {
    const menu = triggerEl.nextElementSibling;
    if (!menu) return;
    const isOpen = menu.classList.contains('cbn-actions-menu-open');
    cbnCloseAllActionsMenus();
    if (!isOpen) {
        menu.style.left = '-9999px';
        menu.style.top = '-9999px';
        menu.classList.add('cbn-actions-menu-open');
        cbnPositionActionsMenu(triggerEl, menu);
    }
}

window.addEventListener('scroll', cbnCloseAllActionsMenus, true);
window.addEventListener('resize', cbnCloseAllActionsMenus);

// close any open action menu when clicking outside of it
document.addEventListener('click', function (e) {
    if (!e.target.closest('.cbn-actions-wrapper')) {
        cbnCloseAllActionsMenus();
    }
});

// a fixed-position menu won't follow the page while scrolling/resizing,
// so just close it instead of leaving it floating in the wrong spot
window.addEventListener('scroll', cbnCloseAllActionsMenus, true);
window.addEventListener('resize', cbnCloseAllActionsMenus);

// create-menu modal open/close
function cbnOpenCreateMenuModal() {
    const overlay = document.getElementById('cbnCreateMenuModalOverlay');
    if (overlay) overlay.classList.add('cbn-modal-overlay-open');
}

function cbnCloseCreateMenuModal() {
    const overlay = document.getElementById('cbnCreateMenuModalOverlay');
    if (overlay) overlay.classList.remove('cbn-modal-overlay-open');
}

document.addEventListener('DOMContentLoaded', function () {
    const openBtn = document.getElementById('cbnOpenCreateMenuModalBtn');
    const closeBtn = document.getElementById('cbnCloseCreateMenuModalBtn');
    const cancelBtn = document.getElementById('cbnCancelCreateMenuBtn');
    const overlay = document.getElementById('cbnCreateMenuModalOverlay');

    if (openBtn) openBtn.addEventListener('click', cbnOpenCreateMenuModal);
    if (closeBtn) closeBtn.addEventListener('click', cbnCloseCreateMenuModal);
    if (cancelBtn) cancelBtn.addEventListener('click', cbnCloseCreateMenuModal);
    if (overlay) {
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) cbnCloseCreateMenuModal();
        });
    }

    // close modal on Escape
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') cbnCloseCreateMenuModal();
    });
});

document.getElementById('cbnSlideMainImage').addEventListener('change', function (e) {
    const file = e.target.files[0];
    const preview = document.getElementById('cbnSlideMainImagePreview');
    if (file) {
        preview.src = URL.createObjectURL(file);
        preview.style.display = 'block';
    } else {
        preview.style.display = 'none';
    }
});

