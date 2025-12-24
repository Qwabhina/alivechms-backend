<?php
$pageTitle = 'Members Management';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="container-fluid py-4">
   <!-- Page Header -->
   <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
         <h1 class="h3 mb-1">Members</h1>
         <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
               <li class="breadcrumb-item"><a href="../dashboard/">Dashboard</a></li>
               <li class="breadcrumb-item active">Members</li>
            </ol>
         </nav>
      </div>
      <button class="btn btn-primary" id="addMemberBtn" data-permission="create_members">
         <i class="bi bi-plus-circle me-2"></i>Add Member
      </button>
   </div>

   <!-- Members Table -->
   <div class="card">
      <div class="card-body">
         <table id="membersTable" class="table table-hover">
            <thead>
               <tr>
                  <th>Photo</th>
                  <th>Name</th>
                  <th>Gender</th>
                  <th>Phone</th>
                  <th>Email</th>
                  <th>Status</th>
                  <th>Actions</th>
               </tr>
            </thead>
            <tbody></tbody>
         </table>
      </div>
   </div>
</div>
</main>

<!-- Member Modal -->
<div class="modal fade" id="memberModal" tabindex="-1">
   <div class="modal-dialog modal-xl">
      <div class="modal-content">
         <div class="modal-header">
            <h5 class="modal-title" id="memberModalTitle">Add Member</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
         </div>
         <div class="modal-body">
            <form id="memberForm">
               <input type="hidden" id="memberId" name="memberId">

               <!-- Tabs Navigation -->
               <ul class="nav nav-tabs mb-4" id="memberTabs" role="tablist">
                  <li class="nav-item">
                     <button class="nav-link active" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic" type="button">
                        <i class="bi bi-person me-2"></i>Basic Info
                     </button>
                  </li>
                  <li class="nav-item">
                     <button class="nav-link" id="contact-tab" data-bs-toggle="tab" data-bs-target="#contact" type="button">
                        <i class="bi bi-telephone me-2"></i>Contact Info
                     </button>
                  </li>
                  <li class="nav-item">
                     <button class="nav-link" id="personal-tab" data-bs-toggle="tab" data-bs-target="#personal" type="button">
                        <i class="bi bi-info-circle me-2"></i>Personal Info
                     </button>
                  </li>
                  <li class="nav-item">
                     <button class="nav-link" id="family-tab" data-bs-toggle="tab" data-bs-target="#family" type="button">
                        <i class="bi bi-people me-2"></i>Family Info
                     </button>
                  </li>
                  <li class="nav-item">
                     <button class="nav-link" id="login-tab" data-bs-toggle="tab" data-bs-target="#login" type="button">
                        <i class="bi bi-key me-2"></i>Login Info
                     </button>
                  </li>
               </ul>

               <!-- Tabs Content -->
               <div class="tab-content" id="memberTabsContent">
                  <!-- Basic Info Tab -->
                  <div class="tab-pane fade show active" id="basic" role="tabpanel">
                     <div class="row">
                        <div class="col-md-12 mb-4 text-center">
                           <div class="profile-picture-upload">
                              <div class="profile-picture-preview" id="profilePicturePreview">
                                 <i class="bi bi-person-circle"></i>
                              </div>
                              <input type="file" id="profilePicture" name="profilePicture" accept="image/*" class="d-none">
                              <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="uploadPhotoBtn">
                                 <i class="bi bi-camera me-1"></i>Upload Photo
                              </button>
                              <button type="button" class="btn btn-sm btn-outline-danger mt-2 d-none" id="removePhotoBtn">
                                 <i class="bi bi-trash me-1"></i>Remove
                              </button>
                           </div>
                        </div>
                        <div class="col-md-4 mb-3">
                           <label class="form-label">First Name <span class="text-danger">*</span></label>
                           <input type="text" class="form-control" id="firstName" name="firstName" required>
                        </div>
                        <div class="col-md-4 mb-3">
                           <label class="form-label">Family Name <span class="text-danger">*</span></label>
                           <input type="text" class="form-control" id="familyName" name="familyName" required>
                        </div>
                        <div class="col-md-4 mb-3">
                           <label class="form-label">Other Names</label>
                           <input type="text" class="form-control" id="otherNames" name="otherNames">
                        </div>
                        <div class="col-md-4 mb-3">
                           <label class="form-label">Gender <span class="text-danger">*</span></label>
                           <select class="form-select" id="gender" name="gender" required>
                              <option value="">Select Gender</option>
                              <option value="Male">Male</option>
                              <option value="Female">Female</option>
                              <option value="Other">Other</option>
                           </select>
                        </div>
                        <div class="col-md-4 mb-3">
                           <label class="form-label">Date of Birth</label>
                           <input type="text" class="form-control" id="dateOfBirth" name="dateOfBirth">
                        </div>
                        <div class="col-md-4 mb-3">
                           <label class="form-label">Membership Status <span class="text-danger">*</span></label>
                           <select class="form-select" id="membershipStatus" name="membershipStatus" required>
                              <option value="Active">Active</option>
                              <option value="Inactive">Inactive</option>
                              <option value="Suspended">Suspended</option>
                           </select>
                        </div>
                        <div class="col-md-12 mb-3">
                           <label class="form-label">Registration Date <span class="text-danger">*</span></label>
                           <input type="text" class="form-control" id="registrationDate" name="registrationDate" required>
                        </div>
                     </div>
                  </div>

                  <!-- Contact Info Tab -->
                  <div class="tab-pane fade" id="contact" role="tabpanel">
                     <div class="row">
                        <div class="col-md-6 mb-3">
                           <label class="form-label">Email Address</label>
                           <input type="email" class="form-control" id="email" name="email">
                        </div>
                        <div class="col-md-6 mb-3">
                           <label class="form-label">Residential Address</label>
                           <input type="text" class="form-control" id="address" name="address">
                        </div>
                        <div class="col-md-12 mb-3">
                           <label class="form-label">Phone Numbers</label>
                           <div id="phoneNumbersContainer">
                              <div class="phone-number-row mb-2">
                                 <div class="input-group">
                                    <input type="text" class="form-control phone-number" placeholder="e.g., 0241234567">
                                    <button type="button" class="btn btn-outline-danger remove-phone-btn" style="display: none;">
                                       <i class="bi bi-trash"></i>
                                    </button>
                                 </div>
                              </div>
                           </div>
                           <button type="button" class="btn btn-sm btn-outline-primary" id="addPhoneBtn">
                              <i class="bi bi-plus-circle me-1"></i>Add Phone Number
                           </button>
                        </div>
                     </div>
                  </div>

                  <!-- Personal Info Tab -->
                  <div class="tab-pane fade" id="personal" role="tabpanel">
                     <div class="row">
                        <div class="col-md-6 mb-3">
                           <label class="form-label">Occupation</label>
                           <input type="text" class="form-control" id="occupation" name="occupation">
                        </div>
                        <div class="col-md-6 mb-3">
                           <label class="form-label">Marital Status</label>
                           <select class="form-select" id="maritalStatus" name="maritalStatus">
                              <option value="Single">Single</option>
                              <option value="Married">Married</option>
                              <option value="Divorced">Divorced</option>
                              <option value="Widowed">Widowed</option>
                           </select>
                        </div>
                        <div class="col-md-12 mb-3">
                           <label class="form-label">Highest Education Level</label>
                           <input type="text" class="form-control" id="educationLevel" name="educationLevel">
                        </div>
                     </div>
                  </div>

                  <!-- Family Info Tab -->
                  <div class="tab-pane fade" id="family" role="tabpanel">
                     <div class="row">
                        <div class="col-md-12 mb-3">
                           <label class="form-label">Family</label>
                           <select class="form-select" id="familyId" name="familyId">
                              <option value="">No Family</option>
                           </select>
                        </div>
                        <div class="col-md-12 mb-3">
                           <label class="form-label">Family Role</label>
                           <select class="form-select" id="familyRole" name="familyRole">
                              <option value="">Select Role</option>
                              <option value="Parent">Parent</option>
                              <option value="Spouse">Spouse</option>
                              <option value="Child">Child</option>
                              <option value="Other">Other</option>
                           </select>
                        </div>
                     </div>
                  </div>

                  <!-- Login Info Tab -->
                  <div class="tab-pane fade" id="login" role="tabpanel">
                     <div class="row">
                        <div class="col-md-12 mb-3">
                           <div class="form-check">
                              <input class="form-check-input" type="checkbox" id="hasLoginAccess" name="hasLoginAccess">
                              <label class="form-check-label" for="hasLoginAccess">
                                 Grant login access to this member
                              </label>
                           </div>
                        </div>
                        <div id="loginFieldsContainer" style="display: none;">
                           <div class="col-md-12 mb-3">
                              <label class="form-label">Username</label>
                              <input type="text" class="form-control" id="username" name="username">
                           </div>
                           <div class="col-md-12 mb-3">
                              <label class="form-label">Password</label>
                              <input type="password" class="form-control" id="password" name="password">
                              <small class="text-muted">Leave blank to keep current password</small>
                           </div>
                           <div class="col-md-12 mb-3">
                              <label class="form-label">Role</label>
                              <select class="form-select" id="roleId" name="roleId">
                                 <option value="">Select Role</option>
                              </select>
                           </div>
                        </div>
                     </div>
                  </div>
               </div>
            </form>
         </div>
         <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" id="saveMemberBtn">Save Member</button>
         </div>
      </div>
   </div>
</div>

<!-- View Member Modal -->
<div class="modal fade" id="viewMemberModal" tabindex="-1">
   <div class="modal-dialog modal-lg">
      <div class="modal-content">
         <div class="modal-header">
            <h5 class="modal-title">Member Details</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
         </div>
         <div class="modal-body" id="viewMemberContent">
            <div class="text-center">
               <div class="spinner-border" role="status">
                  <span class="visually-hidden">Loading...</span>
               </div>
            </div>
         </div>
         <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="button" class="btn btn-primary" id="editFromViewBtn">Edit</button>
         </div>
      </div>
   </div>
</div>

<style>
   .profile-picture-preview {
      width: 150px;
      height: 150px;
      border-radius: 50%;
      background: #e2e8f0;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto;
      overflow: hidden;
      border: 3px solid #cbd5e0;
   }

   .profile-picture-preview i {
      font-size: 80px;
      color: #a0aec0;
   }

   .profile-picture-preview img {
      width: 100%;
      height: 100%;
      object-fit: cover;
   }

   .phone-number-row {
      position: relative;
   }

   .member-photo {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      object-fit: cover;
      background: #e2e8f0;
   }

   .member-photo-placeholder {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: #3182ce;
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 600;
      font-size: 0.875rem;
   }

   .view-member-photo {
      width: 120px;
      height: 120px;
      border-radius: 50%;
      object-fit: cover;
      background: #e2e8f0;
      border: 3px solid #cbd5e0;
   }
</style>

<script>
   let membersTable;
   let currentMemberId = null;
   let memberFormMode = 'create';

   document.addEventListener('DOMContentLoaded', async function() {
      // Initialize
      Auth.requireAuth();
      await initializePage();
      initializeEventListeners();
   });

   async function initializePage() {
      try {
         // Initialize DataTable
         membersTable = $('#membersTable').DataTable({
            ajax: {
               url: Config.API_BASE_URL + '/member/all',
               headers: {
                  'Authorization': 'Bearer ' + Auth.getToken()
               },
               dataSrc: function(response) {
                  return response.data || [];
               }
            },
            columns: [{
                  data: 'MbrProfilePicture',
                  orderable: false,
                  render: function(data, type, row) {
                     if (data) {
                        return `<img src="${Config.API_BASE_URL}/${data}" class="member-photo">`;
                     }
                     const initials = Utils.getInitials(row.MbrFirstName + ' ' + row.MbrFamilyName);
                     return `<div class="member-photo-placeholder">${initials}</div>`;
                  }
               },
               {
                  data: null,
                  render: function(data, type, row) {
                     return `${row.MbrFirstName} ${row.MbrFamilyName}`;
                  }
               },
               {
                  data: 'MbrGender'
               },
               {
                  data: 'PrimaryPhone',
                  defaultContent: '-'
               },
               {
                  data: 'MbrEmailAddress',
                  defaultContent: '-'
               },
               {
                  data: 'MbrMembershipStatus',
                  render: function(data) {
                     const statusClass = {
                        'Active': 'success',
                        'Inactive': 'secondary',
                        'Suspended': 'danger'
                     };
                     return `<span class="badge bg-${statusClass[data] || 'secondary'}">${data}</span>`;
                  }
               },
               {
                  data: null,
                  orderable: false,
                  render: function(data, type, row) {
                     return `
                            <button class="btn btn-sm btn-outline-primary view-member-btn" data-id="${row.MbrID}">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-warning edit-member-btn" data-id="${row.MbrID}" data-permission="edit_members">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger delete-member-btn" data-id="${row.MbrID}" data-permission="delete_members">
                                <i class="bi bi-trash"></i>
                            </button>
                        `;
                  }
               }
            ],
            order: [
               [1, 'asc']
            ],
            pageLength: 25
         });

         // Load families and roles for dropdowns
         await loadFamilies();
         await loadRoles();

         // Initialize date pickers
         flatpickr('#dateOfBirth', {
            dateFormat: 'Y-m-d',
            maxDate: 'today'
         });

         flatpickr('#registrationDate', {
            dateFormat: 'Y-m-d',
            defaultDate: new Date()
         });

      } catch (error) {
         console.error('Initialization error:', error);
         Alerts.error('Failed to initialize page');
      }
   }

   function initializeEventListeners() {
      // Add member button
      document.getElementById('addMemberBtn').addEventListener('click', function() {
         if (!Auth.hasPermission(Config.PERMISSIONS.CREATE_MEMBERS)) {
            Auth.requirePermission(Config.PERMISSIONS.CREATE_MEMBERS);
            return;
         }
         openMemberModal('create');
      });

      // Table action buttons
      $('#membersTable').on('click', '.view-member-btn', function() {
         const id = $(this).data('id');
         viewMember(id);
      });

      $('#membersTable').on('click', '.edit-member-btn', function() {
         if (!Auth.hasPermission(Config.PERMISSIONS.EDIT_MEMBERS)) {
            Auth.requirePermission(Config.PERMISSIONS.EDIT_MEMBERS);
            return;
         }
         const id = $(this).data('id');
         openMemberModal('edit', id);
      });

      $('#membersTable').on('click', '.delete-member-btn', async function() {
         if (!Auth.hasPermission(Config.PERMISSIONS.DELETE_MEMBERS)) {
            Auth.requirePermission(Config.PERMISSIONS.DELETE_MEMBERS);
            return;
         }
         const id = $(this).data('id');
         await deleteMember(id);
      });

      // Save member button
      document.getElementById('saveMemberBtn').addEventListener('click', saveMember);

      // Edit from view button
      document.getElementById('editFromViewBtn').addEventListener('click', function() {
         const viewModal = bootstrap.Modal.getInstance(document.getElementById('viewMemberModal'));
         viewModal.hide();
         openMemberModal('edit', currentMemberId);
      });

      // Profile picture upload
      document.getElementById('uploadPhotoBtn').addEventListener('click', function() {
         document.getElementById('profilePicture').click();
      });

      document.getElementById('profilePicture').addEventListener('change', handleProfilePictureChange);

      document.getElementById('removePhotoBtn').addEventListener('click', function() {
         document.getElementById('profilePicture').value = '';
         document.getElementById('profilePicturePreview').innerHTML = '<i class="bi bi-person-circle"></i>';
         this.classList.add('d-none');
      });

      // Phone number management
      document.getElementById('addPhoneBtn').addEventListener('click', addPhoneNumberField);

      $(document).on('click', '.remove-phone-btn', function() {
         $(this).closest('.phone-number-row').remove();
      });

      // Login access toggle
      document.getElementById('hasLoginAccess').addEventListener('change', function() {
         document.getElementById('loginFieldsContainer').style.display = this.checked ? 'block' : 'none';
      });
   }

   function openMemberModal(mode, memberId = null) {
      memberFormMode = mode;
      currentMemberId = memberId;

      const modal = new bootstrap.Modal(document.getElementById('memberModal'));
      const title = mode === 'create' ? 'Add Member' : 'Edit Member';
      document.getElementById('memberModalTitle').textContent = title;

      // Reset form
      document.getElementById('memberForm').reset();
      document.getElementById('memberId').value = '';
      document.getElementById('profilePicturePreview').innerHTML = '<i class="bi bi-person-circle"></i>';
      document.getElementById('removePhotoBtn').classList.add('d-none');

      // Reset to first tab
      const firstTab = new bootstrap.Tab(document.querySelector('#basic-tab'));
      firstTab.show();

      // Reset phone numbers
      document.getElementById('phoneNumbersContainer').innerHTML = `
        <div class="phone-number-row mb-2">
            <div class="input-group">
                <input type="text" class="form-control phone-number" placeholder="e.g., 0241234567">
                <button type="button" class="btn btn-outline-danger remove-phone-btn" style="display: none;">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        </div>
    `;

      if (mode === 'edit' && memberId) {
         loadMemberData(memberId);
      }

      modal.show();
   }

   async function loadMemberData(memberId) {
      try {
         Alerts.loading('Loading member data...');
         const response = await api.get(`member/view/${memberId}`);
         Alerts.closeLoading();

         const member = response.data;

         // Basic info
         document.getElementById('memberId').value = member.MbrID;
         document.getElementById('firstName').value = member.MbrFirstName;
         document.getElementById('familyName').value = member.MbrFamilyName;
         document.getElementById('otherNames').value = member.MbrOtherNames || '';
         document.getElementById('gender').value = member.MbrGender;
         document.getElementById('dateOfBirth').value = member.MbrDateOfBirth || '';
         document.getElementById('membershipStatus').value = member.MbrMembershipStatus;
         document.getElementById('registrationDate').value = member.MbrRegistrationDate;

         // Profile picture
         if (member.MbrProfilePicture) {
            document.getElementById('profilePicturePreview').innerHTML =
               `<img src="${Config.API_BASE_URL}/${member.MbrProfilePicture}">`;
            document.getElementById('removePhotoBtn').classList.remove('d-none');
         }

         // Contact info
         document.getElementById('email').value = member.MbrEmailAddress || '';
         document.getElementById('address').value = member.MbrResidentialAddress || '';

         // Phone numbers
         if (member.phones && member.phones.length > 0) {
            document.getElementById('phoneNumbersContainer').innerHTML = '';
            member.phones.forEach((phone, index) => {
               const html = `
                    <div class="phone-number-row mb-2">
                        <div class="input-group">
                            <input type="text" class="form-control phone-number" value="${phone.PhoneNumber}">
                            <button type="button" class="btn btn-outline-danger remove-phone-btn" ${index === 0 ? 'style="display: none;"' : ''}>
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                `;
               document.getElementById('phoneNumbersContainer').insertAdjacentHTML('beforeend', html);
            });
         }

         // Personal info
         document.getElementById('occupation').value = member.MbrOccupation || '';
         document.getElementById('maritalStatus').value = member.MbrMaritalStatus || 'Single';
         document.getElementById('educationLevel').value = member.MbrHighestEducationLevel || '';

         // Family info
         document.getElementById('familyId').value = member.FamilyID || '';

         // Login info
         if (member.auth) {
            document.getElementById('hasLoginAccess').checked = true;
            document.getElementById('loginFieldsContainer').style.display = 'block';
            document.getElementById('username').value = member.auth.Username;
            document.getElementById('roleId').value = member.auth.RoleID || '';
         }

      } catch (error) {
         console.error('Load member error:', error);
         Alerts.handleApiError(error);
      }
   }

   async function saveMember() {
      try {
         // Validate form
         const form = document.getElementById('memberForm');
         if (!form.checkValidity()) {
            form.reportValidity();
            return;
         }

         Alerts.loading('Saving member...');

         const formData = new FormData();

         // Basic info
         formData.append('MbrFirstName', document.getElementById('firstName').value);
         formData.append('MbrFamilyName', document.getElementById('familyName').value);
         formData.append('MbrOtherNames', document.getElementById('otherNames').value);
         formData.append('MbrGender', document.getElementById('gender').value);
         formData.append('MbrDateOfBirth', document.getElementById('dateOfBirth').value);
         formData.append('MbrMembershipStatus', document.getElementById('membershipStatus').value);
         formData.append('MbrRegistrationDate', document.getElementById('registrationDate').value);

         // Profile picture
         const profilePicture = document.getElementById('profilePicture').files[0];
         if (profilePicture) {
            formData.append('ProfilePicture', profilePicture);
         }

         // Contact info
         formData.append('MbrEmailAddress', document.getElementById('email').value);
         formData.append('MbrResidentialAddress', document.getElementById('address').value);

         // Phone numbers
         const phoneNumbers = [];
         document.querySelectorAll('.phone-number').forEach(input => {
            if (input.value.trim()) {
               phoneNumbers.push(input.value.trim());
            }
         });
         formData.append('PhoneNumbers', JSON.stringify(phoneNumbers));

         // Personal info
         formData.append('MbrOccupation', document.getElementById('occupation').value);
         formData.append('MbrMaritalStatus', document.getElementById('maritalStatus').value);
         formData.append('MbrHighestEducationLevel', document.getElementById('educationLevel').value);

         // Family info
         formData.append('FamilyID', document.getElementById('familyId').value);
         formData.append('FamilyRole', document.getElementById('familyRole').value);

         // Login info
         if (document.getElementById('hasLoginAccess').checked) {
            formData.append('HasLoginAccess', '1');
            formData.append('Username', document.getElementById('username').value);
            const password = document.getElementById('password').value;
            if (password) {
               formData.append('Password', password);
            }
            formData.append('RoleID', document.getElementById('roleId').value);
         } else {
            formData.append('HasLoginAccess', '0');
         }

         let response;
         if (memberFormMode === 'create') {
            response = await api.upload('member/create', formData);
         } else {
            const memberId = document.getElementById('memberId').value;
            response = await api.upload(`member/update/${memberId}`, formData);
         }

         Alerts.closeLoading();
         Alerts.success(response.message || 'Member saved successfully');

         const modal = bootstrap.Modal.getInstance(document.getElementById('memberModal'));
         modal.hide();

         membersTable.ajax.reload();

      } catch (error) {
         Alerts.closeLoading();
         console.error('Save member error:', error);
         Alerts.handleApiError(error);
      }
   }

   async function viewMember(memberId) {
      try {
         currentMemberId = memberId;
         const modal = new bootstrap.Modal(document.getElementById('viewMemberModal'));
         modal.show();

         const response = await api.get(`member/view/${memberId}`);
         const member = response.data;

         const photoHtml = member.MbrProfilePicture ?
            `<img src="${Config.API_BASE_URL}/${member.MbrProfilePicture}" class="view-member-photo">` :
            `<div class="view-member-photo d-flex align-items-center justify-content-center bg-primary text-white">
                <i class="bi bi-person-circle" style="font-size: 60px;"></i>
            </div>`;

         const html = `
            <div class="text-center mb-4">
                ${photoHtml}
                <h4 class="mt-3 mb-1">${member.MbrFirstName} ${member.MbrFamilyName}</h4>
                <span class="badge bg-${member.MbrMembershipStatus === 'Active' ? 'success' : 'secondary'}">${member.MbrMembershipStatus}</span>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <strong>Gender:</strong> ${member.MbrGender || '-'}
                </div>
                <div class="col-md-6 mb-3">
                    <strong>Date of Birth:</strong> ${member.MbrDateOfBirth ? Utils.formatDate(member.MbrDateOfBirth) : '-'}
                </div>
                <div class="col-md-6 mb-3">
                    <strong>Email:</strong> ${member.MbrEmailAddress || '-'}
                </div>
                <div class="col-md-6 mb-3">
                    <strong>Phone:</strong> ${member.phones && member.phones.length > 0 ? member.phones[0].PhoneNumber : '-'}
                </div>
                <div class="col-md-12 mb-3">
                    <strong>Address:</strong> ${member.MbrResidentialAddress || '-'}
                </div>
                <div class="col-md-6 mb-3">
                    <strong>Occupation:</strong> ${member.MbrOccupation || '-'}
                </div>
                <div class="col-md-6 mb-3">
                    <strong>Marital Status:</strong> ${member.MbrMaritalStatus || '-'}
                </div>
                <div class="col-md-12 mb-3">
                    <strong>Education:</strong> ${member.MbrHighestEducationLevel || '-'}
                </div>
                <div class="col-md-6 mb-3">
                    <strong>Registration Date:</strong> ${Utils.formatDate(member.MbrRegistrationDate)}
                </div>
            </div>
        `;

         document.getElementById('viewMemberContent').innerHTML = html;

      } catch (error) {
         console.error('View member error:', error);
         Alerts.handleApiError(error);
      }
   }

   async function deleteMember(memberId) {
      try {
         const confirmed = await Alerts.confirmDelete({
            title: 'Delete Member',
            text: 'Are you sure you want to delete this member?'
         });

         if (!confirmed) return;

         Alerts.loading('Deleting member...');
         const response = await api.delete(`member/delete/${memberId}`);
         Alerts.closeLoading();

         Alerts.success(response.message || 'Member deleted successfully');
         membersTable.ajax.reload();

      } catch (error) {
         Alerts.closeLoading();
         console.error('Delete member error:', error);
         Alerts.handleApiError(error);
      }
   }

   function handleProfilePictureChange(e) {
      const file = e.target.files[0];
      if (!file) return;

      // Validate file type
      if (!file.type.match('image.*')) {
         Alerts.error('Please select a valid image file');
         e.target.value = '';
         return;
      }

      // Validate file size (max 2MB)
      if (file.size > 2 * 1024 * 1024) {
         Alerts.error('Image size must be less than 2MB');
         e.target.value = '';
         return;
      }

      // Show preview
      const reader = new FileReader();
      reader.onload = function(e) {
         document.getElementById('profilePicturePreview').innerHTML =
            `<img src="${e.target.result}">`;
         document.getElementById('removePhotoBtn').classList.remove('d-none');
      };
      reader.readAsDataURL(file);
   }

   function addPhoneNumberField() {
      const html = `
        <div class="phone-number-row mb-2">
            <div class="input-group">
                <input type="text" class="form-control phone-number" placeholder="e.g., 0241234567">
                <button type="button" class="btn btn-outline-danger remove-phone-btn">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        </div>
    `;
      document.getElementById('phoneNumbersContainer').insertAdjacentHTML('beforeend', html);
   }

   async function loadFamilies() {
      try {
         const response = await api.get('family/all');
         const select = document.getElementById('familyId');
         select.innerHTML = '<option value="">No Family</option>';

         response.data.forEach(family => {
            const option = document.createElement('option');
            option.value = family.FamilyID;
            option.textContent = family.FamilyName;
            select.appendChild(option);
         });
      } catch (error) {
         console.error('Load families error:', error);
      }
   }

   async function loadRoles() {
      try {
         const response = await api.get('role/all');
         const select = document.getElementById('roleId');
         select.innerHTML = '<option value="">Select Role</option>';

         response.data.forEach(role => {
            const option = document.createElement('option');
            option.value = role.RoleID;
            option.textContent = role.RoleName;
            select.appendChild(option);
         });
      } catch (error) {
         console.error('Load roles error:', error);
      }
   }
</script>

<?php require_once '../includes/footer.php'; ?>