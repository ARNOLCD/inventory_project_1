<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'config/email.php';
requireStaff();

$user = getCurrentUser();
$message = '';
$error = '';

ensureRepairRequestSchema($conn);

// Handle accept / deny
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = intval($_POST['id'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    if ($action === 'accept') {
        $technician_id = !empty($_POST['technician_id']) ? intval($_POST['technician_id']) : null;
        $priority = in_array($_POST['priority'] ?? '', ['low', 'normal', 'high', 'urgent'], true) ? $_POST['priority'] : 'normal';
        $estimated_cost = ($_POST['estimated_cost'] ?? '') !== '' ? floatval($_POST['estimated_cost']) : null;

        $stmt = $conn->prepare("UPDATE repairs SET status = 'booked', technician_id = ?, priority = ?, estimated_cost = COALESCE(?, estimated_cost),
                                       received_by = ?, reviewed_by = ?, reviewed_date = NOW(),
                                       repair_notes = CONCAT(IFNULL(repair_notes, ''), '\n[" . date('Y-m-d H:i') . "] Request accepted - ', ?)
                                WHERE id = ? AND status = 'pending_approval'");
        $stmt->bind_param("isdiisi", $technician_id, $priority, $estimated_cost, $user['id'], $user['id'], $notes, $id);
        $stmt->execute();

        if ($stmt->affected_rows === 1) {
            notifyRepairCustomer($conn, $id, 'booked', $notes);
            $message = 'Repair request accepted. The customer has been notified by email.';
        } else {
            $error = 'This request could not be accepted. It may already have been reviewed by another user.';
        }
    } elseif ($action === 'deny') {
        if ($notes === '') {
            $error = 'Please provide a reason for declining the request.';
        } else {
            $stmt = $conn->prepare("UPDATE repairs SET status = 'rejected', rejection_reason = ?, reviewed_by = ?, reviewed_date = NOW(),
                                           repair_notes = CONCAT(IFNULL(repair_notes, ''), '\n[" . date('Y-m-d H:i') . "] Request declined - ', ?)
                                    WHERE id = ? AND status = 'pending_approval'");
            $stmt->bind_param("sisi", $notes, $user['id'], $notes, $id);
            $stmt->execute();

            if ($stmt->affected_rows === 1) {
                notifyRepairCustomer($conn, $id, 'rejected', $notes);
                $message = 'Repair request declined. The customer has been notified by email.';
            } else {
                $error = 'This request could not be declined. It may already have been reviewed by another user.';
            }
        }
    }
}

$pending = $conn->query("
    SELECT r.*, u.full_name as account_name, u.email as account_email
    FROM repairs r
    LEFT JOIN users u ON r.customer_id = u.id
    WHERE r.status = 'pending_approval'
    ORDER BY r.created_at ASC
");

$reviewed = $conn->query("
    SELECT r.id, r.ticket_number, r.customer_name, r.device_type, r.device_brand, r.status, r.rejection_reason, r.reviewed_date,
           rv.full_name as reviewer_name
    FROM repairs r
    LEFT JOIN users rv ON r.reviewed_by = rv.id
    WHERE r.reviewed_date IS NOT NULL
    ORDER BY r.reviewed_date DESC
    LIMIT 20
");

$technicians = $conn->query("SELECT id, full_name, role FROM users WHERE role IN ('" . implode("','", STAFF_ROLES) . "') ORDER BY full_name");
$technician_options = $technicians->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Repair Requests - Sims-Tech Zambia</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .request-card { background: white; border-radius: 8px; padding: 20px; margin-bottom: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-left: 4px solid #718096; }
        .request-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; gap: 10px; flex-wrap: wrap; }
        .request-ticket { font-size: 1.1rem; font-weight: 600; color: #1a365d; }
        .request-meta { color: #4a5568; font-size: 0.9rem; }
        .request-problem { background: #f7fafc; padding: 10px; border-radius: 5px; font-size: 0.9rem; margin: 10px 0; }
        .request-actions { display: flex; gap: 8px; justify-content: flex-end; }
        .status-btn { padding: 6px 14px; border: none; border-radius: 4px; cursor: pointer; font-size: 0.85rem; color: white; }
        .status-btn:hover { opacity: 0.85; }
        .btn-accept { background: #38a169; }
        .btn-deny { background: #e53e3e; }
        .status-booked { background: #3182ce; color: white; }
        .status-rejected { background: #e53e3e; color: white; }
        .status-other { background: #a0aec0; color: white; }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-content">
            <?php include 'includes/header.php'; ?>

            <div class="dashboard-content">
                <div class="page-header">
                    <h1><i class="fas fa-inbox"></i> Repair Requests</h1>
                    <p>Review repair requests submitted by customers and accept or decline them</p>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
                <?php endif; ?>

                <h3 style="margin-bottom: 15px;">Pending (<?php echo $pending->num_rows; ?>)</h3>

                <?php if ($pending->num_rows > 0): ?>
                    <?php while ($r = $pending->fetch_assoc()): ?>
                        <div class="request-card">
                            <div class="request-header">
                                <div>
                                    <div class="request-ticket"><?php echo htmlspecialchars($r['ticket_number']); ?></div>
                                    <div class="request-meta">
                                        <i class="fas fa-laptop"></i> <?php echo htmlspecialchars(getRepairDeviceInfo($r)); ?>
                                        <?php if ($r['serial_number']): ?> | S/N: <?php echo htmlspecialchars($r['serial_number']); ?><?php endif; ?>
                                    </div>
                                </div>
                                <div class="request-meta" style="text-align: right;">
                                    <i class="far fa-clock"></i> <?php echo date('M d, Y H:i', strtotime($r['created_at'])); ?>
                                </div>
                            </div>

                            <div class="request-meta">
                                <i class="fas fa-user"></i> <strong><?php echo htmlspecialchars($r['account_name'] ?: $r['customer_name']); ?></strong>
                                <?php if ($r['customer_phone']): ?><span style="margin-left: 15px;"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($r['customer_phone']); ?></span><?php endif; ?>
                                <?php if ($r['customer_email']): ?><span style="margin-left: 15px;"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($r['customer_email']); ?></span><?php endif; ?>
                            </div>

                            <div class="request-problem">
                                <strong>Problem:</strong> <?php echo nl2br(htmlspecialchars($r['problem_description'])); ?>
                            </div>

                            <?php if (!empty($r['item_photo'])): ?>
                                <a href="uploads/repairs/<?php echo htmlspecialchars($r['item_photo']); ?>" target="_blank">
                                    <img src="uploads/repairs/<?php echo htmlspecialchars($r['item_photo']); ?>" alt="Item Photo" style="max-width: 160px; max-height: 120px; border-radius: 8px; border: 2px solid #e2e8f0; object-fit: cover;">
                                </a>
                            <?php endif; ?>

                            <div class="request-actions">
                                <button class="status-btn btn-accept" onclick="openAccept(<?php echo $r['id']; ?>, '<?php echo htmlspecialchars($r['ticket_number'], ENT_QUOTES); ?>')">
                                    <i class="fas fa-check"></i> Accept
                                </button>
                                <button class="status-btn btn-deny" onclick="openDeny(<?php echo $r['id']; ?>, '<?php echo htmlspecialchars($r['ticket_number'], ENT_QUOTES); ?>')">
                                    <i class="fas fa-times"></i> Deny
                                </button>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="card mb-4">
                        <div class="card-body text-center" style="padding: 40px;">
                            <i class="fas fa-inbox" style="font-size: 48px; color: #a0aec0; margin-bottom: 15px;"></i>
                            <p>No pending repair requests</p>
                        </div>
                    </div>
                <?php endif; ?>

                <h3 style="margin: 30px 0 15px;">Recently Reviewed</h3>
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Ticket</th>
                                        <th>Customer</th>
                                        <th>Device</th>
                                        <th>Decision</th>
                                        <th>Reviewed By</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($reviewed->num_rows > 0): ?>
                                        <?php while ($rv = $reviewed->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($rv['ticket_number']); ?></td>
                                                <td><?php echo htmlspecialchars($rv['customer_name']); ?></td>
                                                <td><?php echo htmlspecialchars($rv['device_type'] . ($rv['device_brand'] ? ' - ' . $rv['device_brand'] : '')); ?></td>
                                                <td>
                                                    <?php if ($rv['status'] === 'rejected'): ?>
                                                        <span class="badge status-rejected">Declined</span>
                                                        <div style="font-size: 0.8rem; color: #718096;"><?php echo htmlspecialchars($rv['rejection_reason'] ?? ''); ?></div>
                                                    <?php else: ?>
                                                        <span class="badge status-booked">Accepted</span>
                                                        <div style="font-size: 0.8rem; color: #718096;">Now: <?php echo ucfirst(str_replace('_', ' ', $rv['status'])); ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($rv['reviewer_name'] ?? '-'); ?></td>
                                                <td><?php echo date('M d, Y H:i', strtotime($rv['reviewed_date'])); ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="6" class="text-center">No reviewed requests yet</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Accept Modal -->
    <div class="modal-overlay" id="acceptModal">
        <div class="modal" style="max-width: 450px;">
            <div class="modal-header">
                <h3><i class="fas fa-check-circle"></i> Accept Request <span id="acceptTicket"></span></h3>
                <button class="modal-close" onclick="closeModals()">&times;</button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="accept">
                    <input type="hidden" name="id" id="acceptId">
                    <div class="form-group">
                        <label>Assign Technician (optional)</label>
                        <select name="technician_id" class="form-control">
                            <option value="">-- Unassigned --</option>
                            <?php foreach ($technician_options as $tech): ?>
                                <option value="<?php echo $tech['id']; ?>" <?php echo $tech['id'] == $user['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($tech['full_name']); ?> (<?php echo ucfirst($tech['role']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Priority</label>
                        <select name="priority" class="form-control">
                            <option value="low">Low</option>
                            <option value="normal" selected>Normal</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Estimated Cost (K, optional)</label>
                        <input type="number" name="estimated_cost" class="form-control" step="0.01" min="0">
                    </div>
                    <div class="form-group">
                        <label>Message to customer (optional)</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Please drop off your device at our shop on weekdays 8am-5pm"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModals()">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background: #38a169;"><i class="fas fa-check"></i> Accept Request</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Deny Modal -->
    <div class="modal-overlay" id="denyModal">
        <div class="modal" style="max-width: 450px;">
            <div class="modal-header">
                <h3><i class="fas fa-ban"></i> Deny Request <span id="denyTicket"></span></h3>
                <button class="modal-close" onclick="closeModals()">&times;</button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="deny">
                    <input type="hidden" name="id" id="denyId">
                    <div class="form-group">
                        <label>Reason (sent to the customer) *</label>
                        <textarea name="notes" class="form-control" rows="3" required placeholder="e.g. We do not currently repair this device model"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModals()">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background: #e53e3e;"><i class="fas fa-times"></i> Deny Request</button>
                </div>
            </form>
        </div>
    </div>

    <script src="assets/js/main.js"></script>
    <script>
        function openAccept(id, ticket) {
            document.getElementById('acceptId').value = id;
            document.getElementById('acceptTicket').textContent = ticket;
            document.getElementById('acceptModal').classList.add('active');
        }

        function openDeny(id, ticket) {
            document.getElementById('denyId').value = id;
            document.getElementById('denyTicket').textContent = ticket;
            document.getElementById('denyModal').classList.add('active');
        }

        function closeModals() {
            document.getElementById('acceptModal').classList.remove('active');
            document.getElementById('denyModal').classList.remove('active');
        }

        ['acceptModal', 'denyModal'].forEach(id => {
            document.getElementById(id).addEventListener('click', function(e) {
                if (e.target === this) closeModals();
            });
        });
    </script>
</body>
</html>
