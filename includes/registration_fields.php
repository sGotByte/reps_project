<?php
// This fragment is also safe to include from pages that do not provide
// registration state (for example, a preview or a direct include).
$registrationDefaults = [
   'user_type' => 'student',
   'id_number' => '',
   'first_name' => '',
   'last_name' => '',
   'department_id' => '',
   'phone_number' => '',
   'email' => '',
];
$old = isset($old) && is_array($old)
   ? array_merge($registrationDefaults, $old)
   : $registrationDefaults;
unset($registrationDefaults);
$departments = isset($departments) && is_array($departments) ? $departments : [];
$isStudentOld = $old['user_type'] === 'student';
?>
<div data-registration-fields>
   <?php if (!empty($errors)): ?>
      <div class="auth-alert auth-alert-error auth-alert-stack" role="alert">
         <?php foreach ($errors as $error): ?>
            <div class="flex items-center gap-1.5">
               <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
               <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
         <?php endforeach; ?>
      </div>
   <?php endif; ?>

   <div data-registration-error class="auth-alert auth-alert-error hidden" role="alert">
      <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
      <span data-registration-error-text></span>
   </div>

   <div class="space-y-4">
      <div class="form-row-2">
         <div class="space-y-1.5">
            <label class="auth-label" for="user_type">ประเภทผู้ใช้</label>
            <select id="user_type" name="user_type" required class="<?= $inputClass; ?>">
               <option value="student" <?= $old['user_type'] === 'student' ? 'selected' : ''; ?>>นักศึกษา</option>
               <option value="teacher" <?= $old['user_type'] === 'teacher' ? 'selected' : ''; ?>>ครูอาจารย์</option>
               <option value="staff" <?= $old['user_type'] === 'staff' ? 'selected' : ''; ?>>บุคลากร</option>
            </select>
         </div>
         <div class="space-y-1.5">
            <label class="auth-label" for="id_number" id="id_label"><?= $isStudentOld ? 'รหัสนักศึกษา' : 'ชื่อผู้ใช้'; ?></label>
            <input type="text" id="id_number" name="id_number" required maxlength="20"
               autocomplete="username"
               placeholder="<?= $isStudentOld ? 'เช่น 6620901001' : 'เช่น somchai01'; ?>"
               inputmode="<?= $isStudentOld ? 'numeric' : 'text'; ?>"
               value="<?= htmlspecialchars($old['id_number'], ENT_QUOTES, 'UTF-8'); ?>"
               class="<?= $inputClass; ?>">
         </div>
      </div>

      <div class="form-row-2">
         <div class="space-y-1.5">
            <label class="auth-label" for="first_name">ชื่อจริง</label>
            <input type="text" id="first_name" name="first_name" required autocomplete="given-name"
               placeholder="ชื่อจริง" value="<?= htmlspecialchars($old['first_name'], ENT_QUOTES, 'UTF-8'); ?>"
               class="<?= $inputClass; ?>">
         </div>
         <div class="space-y-1.5">
            <label class="auth-label" for="last_name">นามสกุล</label>
            <input type="text" id="last_name" name="last_name" required autocomplete="family-name"
               placeholder="นามสกุล" value="<?= htmlspecialchars($old['last_name'], ENT_QUOTES, 'UTF-8'); ?>"
               class="<?= $inputClass; ?>">
         </div>
      </div>

      <div class="form-row-2">
         <div class="space-y-1.5">
            <label class="auth-label" for="department_id">แผนกวิชา/สังกัด</label>
            <select id="department_id" name="department_id" required class="<?= $inputClass; ?>">
               <option value="">เลือกแผนกวิชา/สังกัด</option>
               <?php foreach ($departments as $department): ?>
                  <option value="<?= (int)$department['d_id']; ?>" <?= $old['department_id'] === (string)$department['d_id'] ? 'selected' : ''; ?>>
                     <?= htmlspecialchars($department['name'], ENT_QUOTES, 'UTF-8'); ?>
                  </option>
               <?php endforeach; ?>
            </select>
         </div>
         <div class="space-y-1.5">
            <label class="auth-label" for="phone_number">เบอร์โทรศัพท์</label>
            <input type="text" id="phone_number" name="phone_number" required inputmode="numeric" maxlength="10"
               placeholder="08XXXXXXXX"
               value="<?= htmlspecialchars($old['phone_number'], ENT_QUOTES, 'UTF-8'); ?>"
               class="<?= $inputClass; ?>">
         </div>
      </div>

      <div class="space-y-1.5">
         <label class="auth-label" for="email">อีเมล</label>
         <input type="email" id="email" name="email" required autocomplete="email"
            placeholder="name@example.com"
            value="<?= htmlspecialchars($old['email'], ENT_QUOTES, 'UTF-8'); ?>"
            class="<?= $inputClass; ?>">
      </div>

      <div class="space-y-1.5">
         <label class="auth-label" for="password">รหัสผ่าน</label>
         <div class="relative">
            <input type="password" id="password" name="password" required minlength="8"
               autocomplete="new-password" placeholder="อย่างน้อย 8 ตัวอักษร"
               class="<?= $pwdClass; ?>">
            <button type="button" data-password-target="password" aria-label="แสดง/ซ่อนรหัสผ่าน"
               class="<?= $eyeBtnClass; ?>">
               <i class="fa-regular fa-eye text-[15px]"></i>
            </button>
         </div>
      </div>

      <div class="space-y-1.5">
         <label class="auth-label" for="confirm_password">ยืนยันรหัสผ่าน</label>
         <div class="relative">
            <input type="password" id="confirm_password" name="confirm_password" required
               autocomplete="new-password" placeholder="กรอกรหัสผ่านอีกครั้ง"
               class="<?= $pwdClass; ?>">
            <button type="button" data-password-target="confirm_password" aria-label="แสดง/ซ่อนรหัสผ่าน"
               class="<?= $eyeBtnClass; ?>">
               <i class="fa-regular fa-eye text-[15px]"></i>
            </button>
         </div>
      </div>
   </div>
</div>

<script>
   (function() {
      const fields = document.currentScript.previousElementSibling;
      if (!fields || !fields.matches('[data-registration-fields]')) return;

      fields.querySelectorAll('[data-password-target]').forEach(function(button) {
         button.addEventListener('click', function() {
            const input = fields.querySelector('#' + button.dataset.passwordTarget);
            const icon = button.querySelector('i');
            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            icon.className = isHidden ?
               'fa-regular fa-eye-slash text-[15px]' :
               'fa-regular fa-eye text-[15px]';
         });
      });

      const phone = fields.querySelector('#phone_number');
      phone.addEventListener('input', function() {
         this.value = this.value.replace(/\D/g, '');
      });

      // สลับ label / placeholder ของช่องรหัสตามประเภทผู้ใช้
      const userType = fields.querySelector('#user_type');
      const idInput = fields.querySelector('#id_number');
      const idLabel = fields.querySelector('#id_label');

      function applyUserType() {
         const isStudent = userType.value === 'student';
         idLabel.textContent = isStudent ? 'รหัสนักศึกษา' : 'ชื่อผู้ใช้';
         idInput.placeholder = isStudent ? 'เช่น 6620901001' : 'เช่น somchai01';
         idInput.inputMode = isStudent ? 'numeric' : 'text';
      }
      userType.addEventListener('change', function() {
         idInput.value = '';
         applyUserType();
      });
      applyUserType();

      const form = fields.closest('form');
      if (!form) return;

      form.addEventListener('submit', function(event) {
         const value = (name) => fields.querySelector('[name="' + name + '"]').value.trim();
         const password = fields.querySelector('#password').value;
         const confirmPassword = fields.querySelector('#confirm_password').value;
         let message = '';

         if (userType.value === 'student' && !/^\d{8,20}$/.test(value('id_number')))
            message = 'รหัสนักศึกษาต้องเป็นตัวเลข 8-20 หลัก';
         else if (userType.value !== 'student' && !/^[A-Za-z0-9_.\-]{4,20}$/.test(value('id_number')))
            message = 'ชื่อผู้ใช้ต้องเป็นตัวอักษรอังกฤษ ตัวเลข _ . - ความยาว 4-20 ตัว';
         else if (!value('first_name') || !value('last_name')) message = 'กรุณากรอกชื่อและนามสกุล';
         else if (!value('department_id')) message = 'กรุณาเลือกแผนกวิชา/สังกัด';
         else if (!/^0\d{8,9}$/.test(value('phone_number'))) message = 'เบอร์โทรศัพท์ไม่ถูกต้อง';
         else if (!/^\S+@\S+\.\S+$/.test(value('email'))) message = 'รูปแบบอีเมลไม่ถูกต้อง';
         else if (password.length < 8) message = 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร';
         else if (password !== confirmPassword) message = 'รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน';

         const errorBox = fields.querySelector('[data-registration-error]');
         if (message) {
            event.preventDefault();
            fields.querySelector('[data-registration-error-text]').textContent = message;
            errorBox.classList.remove('hidden');
            return;
         }

         errorBox.classList.add('hidden');
         const submitLabel = form.querySelector('[data-registration-submit-label]');
         if (submitLabel) submitLabel.textContent = 'กำลังสร้างบัญชี...';
      });
   })();
</script>