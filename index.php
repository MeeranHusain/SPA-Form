<?php
// index.php
include 'config.php';
$message = "";

// ----- Messages
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'added': $message = "✅ Users added successfully!"; break;
        case 'updated': $message = "✅ User updated successfully!"; break;
        case 'deleted': $message = "🗑️ User deleted successfully!"; break;
    }
}

// ----- Soft Delete
if (isset($_GET['delete_id'])) {
    $delete_id = (int) $_GET['delete_id'];
    try {
        $stmt = $conn->prepare("UPDATE user_practice SET is_deleted = 1 WHERE id = ?");
        $stmt->execute([$delete_id]);
        header("Location: " . $_SERVER['PHP_SELF'] . "?msg=deleted");
        exit;
    } catch (PDOException $e) {
        $message = "❌ Error deleting user: " . $e->getMessage();
    }
}

// ----- Update (single user edit)
if (isset($_POST['update_id'])) {
    $update_id = (int) $_POST['update_id'];
    $user_name = trim($_POST['user_name']);
    $phone     = str_replace(' ', '', $_POST['phone_number']);
    $gender    = $_POST['gender'];
    $department= $_POST['department'];
    $team      = $_POST['team'];
    $country_id = isset($_POST['country']) ? (int)$_POST['country'] : null;
    $state_id   = isset($_POST['state']) ? (int)$_POST['state'] : null;

    if (!preg_match('/^[0-9]{10}$/', $phone)) {
        $message = "⚠️ Phone number must be exactly 10 digits.";
    } else {
        try {
            $stmt = $conn->prepare("UPDATE user_practice SET user_name=?, phone_number=?, gender=?, department=?, team=?, country_id=?, state_id=? WHERE id=?");
            $stmt->execute([$user_name, $phone, $gender, $department, $team, $country_id, $state_id, $update_id]);
            header("Location: " . $_SERVER['PHP_SELF'] . "?msg=updated");
            exit;
        } catch (PDOException $e) {
            $message = "❌ Error updating user: " . $e->getMessage();
        }
    }
}

// ----- Add multiple users (same as your style, now with country & state arrays)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && empty($_POST['update_id'])) {
    if (!empty($_POST['user_name']) && is_array($_POST['user_name'])) {
        $user_names  = $_POST['user_name'];
        $phones      = $_POST['phone_number'];
        $genders     = $_POST['gender'];
        $departments = $_POST['department'];
        $teams       = $_POST['team'];
        $countries   = isset($_POST['country']) ? $_POST['country'] : [];
        $states      = isset($_POST['state']) ? $_POST['state'] : [];

        try {
            $conn->beginTransaction();
            $stmt = $conn->prepare("INSERT INTO user_practice (user_name, phone_number, gender, department, team, country_id, state_id) VALUES (?, ?, ?, ?, ?, ?, ?)");

            foreach ($user_names as $i => $name) {
                $name = trim($name);
                $phone = isset($phones[$i]) ? str_replace(' ', '', trim($phones[$i])) : '';
                $gender = isset($genders[$i]) ? $genders[$i] : null;
                $department = isset($departments[$i]) ? $departments[$i] : null;
                $team = isset($teams[$i]) ? $teams[$i] : null;
                $country_id = isset($countries[$i]) && $countries[$i] !== '' ? (int)$countries[$i] : null;
                $state_id = isset($states[$i]) && $states[$i] !== '' ? (int)$states[$i] : null;

                if (!preg_match('/^[0-9]{10}$/', $phone)) {
                    $conn->rollBack();
                    throw new Exception("⚠️ Invalid phone number for user '{$name}'. Must be exactly 10 digits.");
                }

                $stmt->execute([$name, $phone, $gender, $department, $team, $country_id, $state_id]);
            }

            $conn->commit();
            header("Location: " . $_SERVER['PHP_SELF'] . "?msg=added");
            exit;
        } catch (Exception $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            $message = "❌ Error adding users: " . $e->getMessage();
        }
    } else {
        $message = "⚠️ No user data to save!";
    }
}

// ----- Fetch user to edit
$editUser = null;
if (isset($_GET['edit_id'])) {
    $edit_id = (int) $_GET['edit_id'];
    $stmt = $conn->prepare("SELECT * FROM user_practice WHERE id=?");
    $stmt->execute([$edit_id]);
    $editUser = $stmt->fetch(PDO::FETCH_ASSOC);
}

// ----- Fetch all users (non-deleted) to show in table
$allUsers = $conn->query("
    SELECT u.*, c.country_name, s.state_name
    FROM user_practice u
    LEFT JOIN countries c ON u.country_id = c.id
    LEFT JOIN states s ON u.state_id = s.id
    WHERE u.is_deleted = 0
    ORDER BY u.id ASC
")->fetchAll(PDO::FETCH_ASSOC);

// ----- For export (if ?export=1): include deleted also as you wanted earlier
if (isset($_GET['export'])) {
    $stmt = $conn->query("
        SELECT u.*, c.country_name, s.state_name
        FROM user_practice u
        LEFT JOIN countries c ON u.country_id = c.id
        LEFT JOIN states s ON u.state_id = s.id
        ORDER BY u.id ASC
    ");
    $allExport = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=users_data.xls");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo "<table border='1'>";
    echo "<tr style='background-color:#ADD8E6; font-weight:bold; text-align:center;'>
            <th>ID</th><th>User Name</th><th>Phone Number</th><th>Gender</th><th>Department</th><th>Team</th><th>Country</th><th>State</th><th>Status</th>
          </tr>";
    foreach ($allExport as $u) {
        $status = $u['is_deleted'] == 1 ? 'Deactivated' : 'Active';
        $country = htmlspecialchars($u['country_name'] ?? '');
        $state = htmlspecialchars($u['state_name'] ?? '');
        echo "<tr style='text-align:center;'>
                <td>{$u['id']}</td>
                <td>".htmlspecialchars($u['user_name'])."</td>
                <td>".htmlspecialchars($u['phone_number'])."</td>
                <td>".htmlspecialchars($u['gender'])."</td>
                <td>".htmlspecialchars($u['department'])."</td>
                <td>".htmlspecialchars($u['team'])."</td>
                <td>{$country}</td>
                <td>{$state}</td>
                <td>{$status}</td>
              </tr>";
    }
    echo "</table>";
    exit;
}

// ----- Fetch countries for populating selects (client-side)
$countries = $conn->query("SELECT id, country_name FROM countries ORDER BY country_name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Form (CRUD + Country-State)</title>
    <link rel="stylesheet" href="SPA-Form-Style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .is-invalid { border-color: #dc3545; box-shadow: 0 0 4px rgba(220, 53, 69, 0.5); }
        .deleted-row { background-color: #f8d7da; }
    </style>
</head>
<body>
<div class="container">
    <h2 class="text-center mb-4 text-primary fw-bold">User Form (CRUD + Country/State)</h2>
    <div class="text-end mb-3">
        <button id="themeToggle" class="btn btn-outline-dark btn-sm">🌙 Dark Mode</button>
    </div>


    <?php if($message): ?>
        <div class="alert <?= strpos($message, 'successfully') !== false ? 'alert-success' : 'alert-warning' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- Edit User (same layout you had, now with country & state) -->
    <?php if($editUser): ?>
        <div class="card p-4 mb-4 shadow-sm border-primary">
            <h4 class="text-primary">Edit User (ID: <?= htmlspecialchars($editUser['id']) ?>)</h4>
            <form method="POST" action="">
                <input type="hidden" name="update_id" value="<?= htmlspecialchars($editUser['id']) ?>">
                <div class="row g-2 mt-2">
                    <div class="col-md-4">
                        <input type="text" name="user_name" class="form-control" value="<?= htmlspecialchars($editUser['user_name']) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <input type="text" id="edit_phone" name="phone_number" class="form-control phone-input" value="<?= htmlspecialchars($editUser['phone_number']) ?>" required maxlength="11" inputmode="numeric">
                    </div>
                    <div class="col-md-4">
                        <select name="gender" class="form-select" required>
                            <option value="">Select Gender</option>
                            <option <?= $editUser['gender']=='Male'?'selected':'' ?>>Male</option>
                            <option <?= $editUser['gender']=='Female'?'selected':'' ?>>Female</option>
                            <option <?= $editUser['gender']=='Other'?'selected':'' ?>>Other</option>
                        </select>
                    </div>

                    <div class="col-md-6 mt-2">
                        <select name="department" class="form-select" required>
                            <option value="">Select Department</option>
                            <option <?= $editUser['department']=='IT'?'selected':'' ?>>IT</option>
                            <option <?= $editUser['department']=='Service'?'selected':'' ?>>Service</option>
                            <option <?= $editUser['department']=='Accounts'?'selected':'' ?>>Accounts</option>
                        </select>
                    </div>
                    <div class="col-md-6 mt-2">
                        <select name="team" class="form-select" required>
                            <option value="">Select Team</option>
                            <option <?= $editUser['team']=='Nikhil Sir'?'selected':'' ?>>Nikhil Sir</option>
                            <option <?= $editUser['team']=="Anuradha Ma'am"?'selected':'' ?>>Anuradha Ma'am</option>
                        </select>
                    </div>

                    <!-- Country / State for edit -->
                    <div class="col-md-6 mt-2">
                        <select id="edit_country" name="country" class="form-select" required>
                            <option value="">Select Country</option>
                            <?php foreach($countries as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= ($editUser['country_id'] == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['country_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mt-2">
                        <select id="edit_state" name="state" class="form-select" required>
                            <option value="">Select State</option>
                            <!-- populated by JS below -->
                        </select>
                    </div>

                </div>

                <div class="mt-3 text-center">
                    <button type="submit" class="btn btn-success px-4">Update User</button>
                    <a href="?" class="btn btn-secondary px-4">Cancel</a>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <!-- Add Users Form (multiple) -->
    <form id="userForm" method="POST" action="">
        <div class="d-flex justify-content-end mb-3">
            <button type="button" class="btn btn-primary" onclick="addUserFields()">+ Add User</button>
        </div>
        <div id="userFields"></div>

        <div class="text-center">
            <button type="submit" class="btn btn-success px-4 mt-3">Submit All</button>
        </div>
    </form>

    <!-- Users Table & Export -->
    <div class="table-responsive mt-5">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="text-center mb-0">All Users</h3>
            <a href="?export=1" class="btn btn-success">🔽 Download Excel</a>
        </div>

        <table class="table table-bordered table-hover text-center align-middle">
            <thead class="table-primary">
                <tr>
                    <th>ID</th>
                    <th>User Name</th>
                    <th>Phone Number</th>
                    <th>Gender</th>
                    <th>Department</th>
                    <th>Team</th>
                    <th>Country</th>
                    <th>State</th>
                    <th>Action</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if(count($allUsers) > 0): ?>
                    <?php foreach($allUsers as $user): ?>
                    <tr class="<?= $user['is_deleted'] == 1 ? 'deleted-row' : '' ?>">
                        <td><?= htmlspecialchars($user['id']) ?></td>
                        <td><?= htmlspecialchars($user['user_name']) ?></td>
                        <td><?= htmlspecialchars($user['phone_number']) ?></td>
                        <td><?= htmlspecialchars($user['gender']) ?></td>
                        <td><?= htmlspecialchars($user['department']) ?></td>
                        <td><?= htmlspecialchars($user['team']) ?></td>
                        <td><?= htmlspecialchars($user['country_name'] ?? '') ?></td>
                        <td><?= htmlspecialchars($user['state_name'] ?? '') ?></td>
                        <td>
                            <?php if ($user['is_deleted'] == 0): ?>
                                <a href="?edit_id=<?= $user['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                                <a href="?delete_id=<?= $user['id'] ?>" onclick="return confirm('Are you sure you want to delete this user?');" class="btn btn-sm btn-danger">Delete</a>
                            <?php else: ?>
                                <span class="text-muted">No Action</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $user['is_deleted'] == 1 ? 'Deactivated' : 'Active' ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="10" class="text-muted">No users found!</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
/* ----- Keep existing behaviour (phone formatting, validation, add/remove rows) ----- */
let count = 0;

function addUserFields() {
    count++;
    const container = document.getElementById("userFields");
    const div = document.createElement("div");
    div.className = "user-block border p-3 mb-3 rounded shadow-sm";
    div.innerHTML = `
        <h5 class="mb-3 text-primary fw-bold">User ${count}</h5>
        <div class="row g-2">
            <div class="col-md-4"><input type="text" name="user_name[]" class="form-control" placeholder="User Name" required></div>
            <div class="col-md-4"><input type="text" name="phone_number[]" class="form-control phone-input" placeholder="Phone Number (10 digits)" required maxlength="11" inputmode="numeric"></div>
            <div class="col-md-4">
                <select name="gender[]" class="form-select gender" required>
                    <option value="">Select Gender</option>
                    <option>Male</option><option>Female</option><option>Other</option>
                </select>
            </div>

            <!-- Country & State -->
            <div class="col-md-6 mt-2">
                <select name="country[]" class="form-select country" required>
                    <option value="">Select Country</option>
                </select>
            </div>
            <div class="col-md-6 mt-2">
                <select name="state[]" class="form-select state" required disabled>
                    <option value="">Select State</option>
                </select>
            </div>

            <div class="col-md-6 mt-2">
                <select name="department[]" class="form-select department" required disabled>
                    <option value="">Select Department</option>
                    <option>IT</option><option>Service</option><option>Accounts</option>
                </select>
            </div>
            <div class="col-md-6 mt-2">
                <select name="team[]" class="form-select team" required disabled>
                    <option value="">Select Team</option>
                    <option>Nikhil Sir</option><option>Anuradha Ma'am</option>
                </select>
            </div>
        </div>
        <button type="button" class="btn btn-danger btn-sm" onclick="removeUser(this)">Remove</button>
    `;
    container.appendChild(div);
    reindexUsers();
    setupDependencies(div);
    populateCountries(div);
}

function removeUser(btn) {
    btn.parentElement.remove();
    reindexUsers();
}

function reindexUsers() {
    const users = document.querySelectorAll('.user-block');
    users.forEach((user, index) => { user.querySelector('h5').innerText = `User ${index + 1}`; });
    count = users.length;
}

/* -- existing dependency behaviour (gender -> department/team) -- */
function setupDependencies(userBlock) {
    const gender = userBlock.querySelector('.gender');
    const department = userBlock.querySelector('.department');
    const team = userBlock.querySelector('.team');

    gender.addEventListener('change', function() {
        department.disabled = gender.value === "";
        if (department.disabled) { department.value = ""; team.value = ""; team.disabled = true; }
    });

    department.addEventListener('change', function() {
        team.disabled = department.value === "";
        if (team.disabled) team.value = "";
    });
}

/* -- Phone formatting and submit validation (exactly 10 digits) -- */
document.addEventListener('input', function(e) {
    if (e.target.classList.contains('phone-input')) {
        let val = e.target.value.replace(/\D/g, '');
        if (val.length > 10) val = val.slice(0, 10);
        // formatting with a space after 5 digits (as you had)
        if (val.length > 5) val = val.slice(0,5) + ' ' + val.slice(5);
        e.target.value = val;
    }
});

setTimeout(() => {
    const alert = document.querySelector('.alert');
    if (alert) alert.style.display = 'none';
}, 3000);

document.getElementById('userForm').addEventListener('submit', function(e) {
    const phoneInputs = document.querySelectorAll('.phone-input');
    let isValid = true;
    phoneInputs.forEach(input => {
        const value = input.value.trim().replace(/\s/g, '');
        if (!/^[0-9]{10}$/.test(value)) {
            input.classList.add('is-invalid');
            isValid = false;
        } else {
            input.classList.remove('is-invalid');
        }
    });
    if (!isValid) {
        e.preventDefault();
        alert('⚠️ Each phone number must contain exactly 10 digits.');
    }
});

/* ----- Country/State logic ----- */
const countriesList = <?= json_encode($countries) ?>;

function populateCountries(userBlock) {
    const countrySelect = userBlock.querySelector('.country');
    countrySelect.innerHTML = '<option value="">Select Country</option>';
    countriesList.forEach(c => {
        const option = document.createElement('option');
        option.value = c.id;
        option.textContent = c.country_name;
        countrySelect.appendChild(option);
    });
    setupCountryStateDependency(userBlock);
}

function setupCountryStateDependency(userBlock) {
    const countrySelect = userBlock.querySelector('.country');
    const stateSelect = userBlock.querySelector('.state');

    countrySelect.addEventListener('change', function() {
        const countryId = this.value;
        stateSelect.innerHTML = '<option value="">Select State</option>';
        stateSelect.disabled = true;
    
        if (countryId) {
            fetch(`get_states.php?country_id=${countryId}`)
                .then(res => res.json())
                .then(states => {
                    states.forEach(st => {
                        const opt = document.createElement('option');
                        opt.value = st.id;
                        opt.textContent = st.state_name;
                        stateSelect.appendChild(opt);
                    });
                    stateSelect.disabled = false;
                })
                .catch(err => {
                    console.error('Error loading states:', err);
                });
        }
    });
}


/* ----- For Edit form: populate edit_state on load when editing ----- */
<?php if($editUser): ?>
// When edit form exists, load states for selected country and set selected state
(function(){
    const editCountry = document.getElementById('edit_country');
    const editState  = document.getElementById('edit_state');
    function loadEditStates(countryId, selectedStateId = null) {
        editState.innerHTML = '<option value="">Select State</option>';
        if (!countryId) return;
        fetch(`get_states.php?country_id=${countryId}`)
            .then(res => res.json())
            .then(states => {
                states.forEach(st => {
                    const opt = document.createElement('option');
                    opt.value = st.id;
                    opt.textContent = st.state_name;
                    if (selectedStateId && selectedStateId == st.id) opt.selected = true;
                    editState.appendChild(opt);
                });
            })
            .catch(err => console.error(err));
    }

    // load on page load with current values
    document.addEventListener('DOMContentLoaded', function(){
        const currentCountry = <?= json_encode((int)($editUser['country_id'] ?? 0)) ?>;
        const currentState   = <?= json_encode((int)($editUser['state_id'] ?? 0)) ?>;
        if (currentCountry) {
            loadEditStates(currentCountry, currentState);
        }
    });

    // load when country changes
    editCountry.addEventListener('change', function(){
        loadEditStates(this.value, null);
    });
})();
<?php endif; ?>

/* ----- Dark Mode Toggle ----- */
const themeToggle = document.getElementById('themeToggle');
const body = document.body;

if (localStorage.getItem('theme') === 'dark') {
  body.classList.add('dark-mode');
  themeToggle.textContent = '☀️ Light Mode';
}

themeToggle.addEventListener('click', () => {
  body.classList.toggle('dark-mode');
  const darkModeOn = body.classList.contains('dark-mode');
  themeToggle.textContent = darkModeOn ? '☀️ Light Mode' : '🌙 Dark Mode';
  localStorage.setItem('theme', darkModeOn ? 'dark' : 'light');
});

</script>
</body>
</html>
