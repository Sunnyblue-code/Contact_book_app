<?php
require_once 'auth/protect.php';
$csrfToken = protectPage();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Book App</title>
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/persist@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/focus@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        .modal {
            background: rgba(0, 0, 0, 0.5);
        }

        .modal-backdrop {
            display: none;
        }

        .toast {
            position: fixed;
            bottom: 1rem;
            right: 1rem;
            z-index: 9999;
            transition: all 0.3s ease;
        }

        [x-cloak] {
            display: none !important;
        }

        .favorite {
            color: gold;
        }

        .loading {
            opacity: 0.5;
            pointer-events: none;
        }

        [x-cloak] {
            display: none !important;
        }

        .fade-enter {
            opacity: 0;
            transform: translateY(-10px);
        }

        .fade-enter-active {
            transition: all 0.2s ease-out;
        }

        .fade-leave-active {
            transition: all 0.2s ease-in;
        }

        .fade-leave-to {
            opacity: 0;
            transform: translateY(10px);
        }

        .contact-card {
            transition: all 0.3s ease;
        }

        .contact-card.dragging {
            opacity: 0.5;
            transform: scale(0.95);
        }

        .drop-target {
            border: 2px dashed #007bff;
        }

        .favorite {
            color: gold;
            transform: scale(1.1);
        }
    </style>
</head>

<body>
    <div class="container mt-4" x-data="contactBook()" x-init="init()" @keydown.ctrl.f.prevent="$refs.searchInput.focus()" @keydown.escape="closeAllModals">

        <!-- Toast Messages -->
        <div class="toast-container" x-show="toast.show" x-transition.duration.300ms style="position: fixed; top: 1rem; right: 1rem; z-index: 9999" @mouseover="clearToastTimeout" @mouseleave="startToastTimeout" x-cloak>
            <div class="toast show" role="alert" :class="toast.type">
                <div class="toast-header">
                    <strong class="me-auto" x-text="toast.title"></strong>
                    <button type="button" class="btn-close" @click="hideToast"></button>
                </div>
                <div class="toast-body" x-text="toast.message"></div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Contact Book</h1>
            <div class="d-flex align-items-center">
                <span class="me-3">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <button class="btn btn-outline-danger" @click="logout">Logout</button>
            </div>
        </div>

        <!-- Search Bar -->
        <div class="mb-4">
            <input type="text" class="form-control" placeholder="Search contacts... (Ctrl+F)" x-model="searchQuery" @input="filterContacts()" x-ref="searchInput">
        </div>

        <!-- Add buttons after search bar -->
        <div class="mb-4 d-flex gap-2">
            <button class="btn btn-primary" @click="showAddModal = true">Add New Contact</button>
            <button class="btn btn-success" @click="exportToCsv()">Export CSV</button>
            <div class="d-inline-block">
                <input type="file" id="csvFile" accept=".csv" class="d-none" @change="importFromCsv($event)">
                <button class="btn btn-info" @click="document.getElementById('csvFile').click()">Import CSV</button>
            </div>
        </div>

        <!-- Sorting Controls -->
        <div class="mb-3">
            <label class="me-2">Sort by:</label>
            <select class="form-select d-inline-block w-auto" x-model="sortField" @change="loadContacts()">
                <option value="name">Name</option>
                <option value="category">Category</option>
                <option value="created_at">Date Added</option>
            </select>
            <select class="form-select d-inline-block w-auto ms-2" x-model="sortOrder" @change="loadContacts()">
                <option value="asc">Ascending</option>
                <option value="desc">Descending</option>
            </select>
        </div>

        <!-- Contact List -->
        <div id="contact-list" class="row" :class="{ 'loading': isLoading }">
            <template x-for="contact in filteredContacts" :key="contact.id">
                <div class="col-md-6 mb-3" draggable="true" @dragstart="dragStart($event, contact)" @dragend="dragEnd($event)" @dragover.prevent="dragOver($event)" @drop="drop($event, contact)">
                    <div class="card contact-card" :class="{ 
                            'border-primary': contact.is_favorite,
                            'dragging': draggingContact?.id === contact.id
                         }" @dblclick="viewContactDetails(contact)" x-show="true" x-transition:enter="fade-enter" x-transition:enter-start="fade-enter" x-transition:enter-end="fade-enter-active">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <h5 class="card-title" x-text="contact.name"></h5>
                                <button class="btn btn-link p-0" @click="toggleFavorite(contact)" :class="{ 'favorite': contact.is_favorite }">
                                    ★
                                </button>
                            </div>
                            <p class="card-text">
                                <strong>Phone:</strong> <span x-text="contact.phone"></span><br>
                                <strong>Email:</strong> <span x-text="contact.email"></span><br>
                                <strong>Category:</strong> <span x-text="contact.category"></span><br>
                                <strong>Description:</strong> <span x-text="contact.description ? contact.description.substring(0, 50) + '...' : ''"></span>
                            </p>
                            <div class="btn-group">
                                <button class="btn btn-sm btn-info" @click="viewContactDetails(contact)">View Details</button>
                                <button class="btn btn-sm btn-primary" @click="editContact(contact)">Edit</button>
                                <button class="btn btn-sm btn-danger" @click="deleteContact(contact.id)">Delete</button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- Pagination Controls -->
        <div class="d-flex justify-content-between align-items-center mt-4">
            <div>
                Showing <span x-text="(currentPage - 1) * perPage + 1"></span>
                to <span x-text="Math.min(currentPage * perPage, totalContacts)"></span>
                of <span x-text="totalContacts"></span> contacts
            </div>
            <div>
                <button class="btn btn-sm btn-secondary" @click="changePage(currentPage - 1)" :disabled="currentPage === 1">
                    Previous
                </button>
                <span class="mx-2">Page <span x-text="currentPage"></span> of <span x-text="totalPages"></span></span>
                <button class="btn btn-sm btn-secondary" @click="changePage(currentPage + 1)" :disabled="currentPage === totalPages">
                    Next
                </button>
            </div>
        </div>

        <!-- Add Contact Modal -->
        <div class="modal" x-show="showAddModal" style="display: none;" :class="{ 'd-block': showAddModal }">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Contact</h5>
                        <button type="button" class="btn-close" @click="closeAddModal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="addContactForm" @submit.prevent="addContact">
                            <div class="mb-3">
                                <label class="form-label">Name</label>
                                <input type="text" class="form-control" x-model="newContact.name" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Phone</label>
                                <input type="tel" class="form-control" x-model="newContact.phone" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" x-model="newContact.email">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Address</label>
                                <textarea class="form-control" x-model="newContact.address"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" x-model="newContact.description"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Category</label>
                                <select class="form-control" x-model="newContact.category">
                                    <option value="General">General</option>
                                    <option value="Family">Family</option>
                                    <option value="Friends">Friends</option>
                                    <option value="Work">Work</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary">Add Contact</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Contact Modal -->
        <div class="modal" x-show="showEditModal" style="display: none;" :class="{ 'd-block': showEditModal }">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Contact</h5>
                        <button type="button" class="btn-close" @click="closeEditModal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="editContactForm" @submit.prevent="updateContact">
                            <input type="hidden" x-model="editingContact.id">
                            <div class="mb-3">
                                <label class="form-label">Name</label>
                                <input type="text" class="form-control" x-model="editingContact.name" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Phone</label>
                                <input type="tel" class="form-control" x-model="editingContact.phone" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" x-model="editingContact.email">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Address</label>
                                <textarea class="form-control" x-model="editingContact.address"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" x-model="editingContact.description"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Category</label>
                                <select class="form-control" x-model="editingContact.category">
                                    <option value="General">General</option>
                                    <option value="Family">Family</option>
                                    <option value="Friends">Friends</option>
                                    <option value="Work">Work</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary">Update Contact</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contact Details Modal -->
        <div class="modal" x-show="showDetailsModal" style="display: none;" :class="{ 'd-block': showDetailsModal }">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Contact Details</h5>
                        <button type="button" class="btn-close" @click="closeDetailsModal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="fw-bold">Name:</label>
                            <p x-text="selectedContact.name"></p>
                        </div>
                        <div class="mb-3">
                            <label class="fw-bold">Phone:</label>
                            <p x-text="selectedContact.phone"></p>
                        </div>
                        <div class="mb-3">
                            <label class="fw-bold">Email:</label>
                            <p x-text="selectedContact.email || 'Not provided'"></p>
                        </div>
                        <div class="mb-3">
                            <label class="fw-bold">Address:</label>
                            <p x-text="selectedContact.address || 'Not provided'"></p>
                        </div>
                        <div class="mb-3">
                            <label class="fw-bold">Description:</label>
                            <p x-text="selectedContact.description || 'Not provided'"></p>
                        </div>
                        <div class="mb-3">
                            <label class="fw-bold">Category:</label>
                            <p x-text="selectedContact.category"></p>
                        </div>
                        <div class="mb-3">
                            <label class="fw-bold">Added on:</label>
                            <p x-text="formatDate(selectedContact.created_at)"></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function contactBook() {
            return {
                showAddModal: false,
                showEditModal: false,
                showDetailsModal: false,
                searchQuery: '',
                contacts: [],
                newContact: {
                    name: '',
                    phone: '',
                    email: '',
                    address: '',
                    description: '',
                    category: 'General'
                },
                editingContact: {},
                selectedContact: {},
                filteredContacts: [],
                currentPage: 1,
                perPage: 10,
                totalContacts: 0,
                totalPages: 1,
                sortField: 'name',
                sortOrder: 'asc',
                csrfToken: '<?php echo $csrfToken; ?>',
                toast: {
                    show: false,
                    type: '',
                    title: '',
                    message: '',
                    timeout: null
                },
                draggingContact: null,
                favorites: Alpine.$persist([]).as('contact-favorites'),
                isLoading: false,

                showToast(title, message, type = 'bg-success text-white') {
                    this.toast = {
                        show: true,
                        type,
                        title,
                        message
                    };
                    this.startToastTimeout();
                },

                startToastTimeout() {
                    if (this.toast.timeout) clearTimeout(this.toast.timeout);
                    this.toast.timeout = setTimeout(() => this.hideToast(), 3000);
                },

                clearToastTimeout() {
                    if (this.toast.timeout) clearTimeout(this.toast.timeout);
                },

                hideToast() {
                    this.toast.show = false;
                },

                dragStart(event, contact) {
                    this.draggingContact = contact;
                    event.dataTransfer.effectAllowed = 'move';
                },

                dragEnd() {
                    this.draggingContact = null;
                },

                dragOver(event) {
                    event.target.closest('.contact-card')?.classList.add('drop-target');
                },

                async drop(event, targetContact) {
                    event.target.closest('.contact-card')?.classList.remove('drop-target');
                    if (!this.draggingContact || this.draggingContact.id === targetContact.id) return;

                    try {
                        const response = await fetch('api/contacts.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken
                            },
                            body: JSON.stringify({
                                action: 'reorder',
                                sourceId: this.draggingContact.id,
                                targetId: targetContact.id
                            })
                        });

                        if (!response.ok) throw new Error('Reorder failed');

                        await this.loadContacts();
                        this.showToast('Success', 'Contacts reordered successfully');
                    } catch (error) {
                        this.showToast('Error', error.message, 'bg-danger text-white');
                    }
                },

                async toggleFavorite(contact) {
                    try {
                        const response = await fetch('api/contacts.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken
                            },
                            body: JSON.stringify({
                                action: 'toggle_favorite',
                                id: contact.id
                            })
                        });

                        if (!response.ok) {
                            throw new Error('Failed to toggle favorite');
                        }

                        const result = await response.json();
                        if (result.success) {
                            contact.is_favorite = result.is_favorite;
                            this.showToast('Success',
                                `Contact ${contact.is_favorite ? 'added to' : 'removed from'} favorites`
                            );
                        } else {
                            throw new Error(result.error || 'Failed to toggle favorite');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        this.showToast('Error', error.message, 'bg-danger text-white');
                    }
                },

                async loadContacts() {
                    this.isLoading = true;
                    try {
                        const queryParams = new URLSearchParams({
                            action: 'get',
                            page: this.currentPage,
                            perPage: this.perPage,
                            sort: this.sortField,
                            order: this.sortOrder
                        });

                        const response = await fetch(`api/contacts.php?${queryParams}`, {
                            headers: {
                                'X-CSRF-TOKEN': this.csrfToken
                            }
                        });
                        const data = await response.json();
                        this.contacts = data.contacts;
                        this.totalContacts = data.total;
                        this.totalPages = Math.ceil(this.totalContacts / this.perPage);
                        this.filterContacts();
                    } catch (error) {
                        this.showToast('Error', error.message, 'bg-danger text-white');
                    } finally {
                        this.isLoading = false;
                    }
                },

                filterContacts() {
                    const query = this.searchQuery.toLowerCase();
                    this.filteredContacts = this.contacts.filter(contact =>
                        contact.name.toLowerCase().includes(query) ||
                        contact.phone.includes(query) ||
                        contact.email.toLowerCase().includes(query)
                    );
                },

                closeAddModal() {
                    this.showAddModal = false;
                    this.newContact = {
                        name: '',
                        phone: '',
                        email: '',
                        address: '',
                        description: '',
                        category: 'General'
                    };
                },

                async addContact() {
                    try {
                        if (!this.newContact.name || !this.newContact.phone) {
                            throw new Error('Name and Phone are required');
                        }

                        const formData = {
                            action: 'add',
                            name: this.newContact.name,
                            phone: this.newContact.phone,
                            email: this.newContact.email || '',
                            address: this.newContact.address || '',
                            description: this.newContact.description || '',
                            category: this.newContact.category || 'General'
                        };

                        const response = await fetch('api/contacts.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken
                            },
                            body: JSON.stringify(formData)
                        });

                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }

                        const result = await response.json();

                        if (!result.success) {
                            throw new Error(result.error || 'Failed to add contact');
                        }

                        await this.loadContacts();
                        this.showAddModal = false; // Explicitly set modal to false
                        this.newContact = { // Reset form
                            name: '',
                            phone: '',
                            email: '',
                            address: '',
                            description: '',
                            category: 'General'
                        };
                        this.showToast('Success', 'Contact added successfully!');
                    } catch (error) {
                        console.error('Error adding contact:', error);
                        this.showToast('Error', error.message, 'bg-danger text-white');
                    }
                },

                editContact(contact) {
                    this.editingContact = {
                        id: contact.id,
                        name: contact.name,
                        phone: contact.phone,
                        email: contact.email || '',
                        address: contact.address || '',
                        description: contact.description || '',
                        category: contact.category || 'General'
                    };
                    this.showEditModal = true;
                },

                closeEditModal() {
                    this.showEditModal = false;
                    this.editingContact = {};
                },

                async updateContact() {
                    try {
                        if (!this.editingContact.name || !this.editingContact.phone) {
                            throw new Error('Name and Phone are required');
                        }

                        const response = await fetch('api/contacts.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken
                            },
                            body: JSON.stringify({
                                action: 'edit',
                                ...this.editingContact
                            })
                        });

                        const result = await response.json();

                        if (!result.success) {
                            throw new Error(result.error || 'Failed to update contact');
                        }

                        await this.loadContacts();
                        this.closeEditModal();
                        this.showToast('Success', 'Contact updated successfully!');
                    } catch (error) {
                        console.error('Error updating contact:', error);
                        this.showToast('Error', error.message, 'bg-danger text-white');
                    }
                },

                async deleteContact(id) {
                    if (!confirm('Are you sure you want to delete this contact?')) return;

                    const formData = new FormData();
                    formData.append('action', 'delete');
                    formData.append('id', id);

                    const response = await fetch('api/contacts.php', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': this.csrfToken
                        },
                        body: formData
                    });
                    const result = await response.json();
                    if (result.success) {
                        this.loadContacts();
                    }
                },

                exportToCsv() {
                    const headers = ['Name', 'Phone', 'Email', 'Address', 'Category'];
                    const csvContent = [
                        headers.join(','),
                        ...this.contacts.map(contact => [
                            contact.name,
                            contact.phone,
                            contact.email,
                            contact.address,
                            contact.category
                        ].map(field => {
                            // Handle null or undefined fields
                            if (field === null || field === undefined) {
                                return '""';
                            }
                            // Escape quotes and wrap field in quotes
                            const escaped = field.toString().replace(/"/g, '""');
                            // Always wrap in quotes to handle commas and line breaks
                            return `"${escaped}"`;
                        }).join(','))
                    ].join('\n');

                    const blob = new Blob([csvContent], {
                        type: 'text/csv;charset=utf-8;'
                    });
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'contacts.csv';
                    a.click();
                    window.URL.revokeObjectURL(url);
                },

                async importFromCsv(event) {
                    const file = event.target.files[0];
                    if (!file) return;

                    const formData = new FormData();
                    formData.append('action', 'import');
                    formData.append('csvFile', file);

                    const response = await fetch('api/contacts.php', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': this.csrfToken
                        },
                        body: formData
                    });

                    const result = await response.json();
                    if (result.success) {
                        this.loadContacts();
                        this.showToast('Success', `Successfully imported ${result.count} contacts`);
                    } else {
                        this.showToast('Error', 'Error importing contacts: ' + result.error, 'bg-danger text-white');
                    }

                    event.target.value = ''; // Reset file input
                },

                changePage(page) {
                    if (page >= 1 && page <= this.totalPages) {
                        this.currentPage = page;
                        this.loadContacts();
                    }
                },

                viewContactDetails(contact) {
                    this.selectedContact = {
                        ...contact,
                        created_at: contact.created_at || new Date().toISOString()
                    };
                    this.showDetailsModal = true;
                },

                closeDetailsModal() {
                    this.showDetailsModal = false;
                    this.selectedContact = {};
                },

                async logout() {
                    try {
                        const response = await fetch('auth/logout.php', {
                            headers: {
                                'X-CSRF-TOKEN': this.csrfToken
                            }
                        });
                        window.location.href = 'login.php';
                    } catch (error) {
                        console.error('Logout failed:', error);
                    }
                },

                formatDate(dateString) {
                    if (!dateString) return 'Unknown';
                    return new Date(dateString).toLocaleString();
                },

                init() {
                    this.loadContacts();

                    // Global keyboard shortcuts
                    document.addEventListener('keydown', (e) => {
                        if (e.ctrlKey && e.key === 'n') {
                            e.preventDefault();
                            this.showAddModal = true;
                        }
                        if (e.key === 'Escape') {
                            this.closeAllModals();
                        }
                    });
                },

                closeAllModals() {
                    this.showAddModal = false;
                    this.showEditModal = false;
                    this.showDetailsModal = false;
                }
            }
        }
    </script>
</body>

</html>