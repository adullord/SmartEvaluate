<?php
require_once '_bootstrap.php';

function formatThaiShortDate(?string $dateValue): string
{
    if (!$dateValue) return '-';
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $dateValue);
    if (!$date) return $dateValue;

    $months = [1 => 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $buddhistYearShort = str_pad((string)(($date->format('Y') + 543) % 100), 2, '0', STR_PAD_LEFT);
    return (int)$date->format('j') . ' ' . $months[(int)$date->format('n')] . ' ' . $buddhistYearShort;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        verifyAdminCsrf();
        if ($action === 'add') {
            $fiscalYear = filter_var($_POST['fiscal_year'] ?? null, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 2500, 'max_range' => 9999],
            ]);
            $roundName = filter_var($_POST['round_name'] ?? null, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1, 'max_range' => 2],
            ]);
            if ($fiscalYear === false) {
                throw new InvalidArgumentException('กรุณากรอกปีงบประมาณ พ.ศ. เป็นตัวเลข 4 หลัก');
            }
            if ($roundName === false) {
                throw new InvalidArgumentException('กรุณาเลือกรอบที่ 1 หรือรอบที่ 2');
            }

            $gregorianFiscalYear = $fiscalYear - 543;
            if ($roundName === 1) {
                $startDate = ($gregorianFiscalYear - 1) . '-10-01';
                $endDate = $gregorianFiscalYear . '-03-31';
            } else {
                $startDate = $gregorianFiscalYear . '-04-01';
                $endDate = $gregorianFiscalYear . '-09-30';
            }

            $stmt = $pdo->prepare('INSERT INTO evaluation_cycles (fiscal_year, round_name, start_date, end_date, status) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([
                (string)$fiscalYear, (string)$roundName, $startDate, $endDate, 'closed',
            ]);
            adminRedirect('cycles.php', 'success', 'เพิ่มรอบประเมินเรียบร้อย');
        }
        if ($action === 'open') {
            $id = requestInt($_POST['id'] ?? null, 'id');
            $pdo->beginTransaction();
            $pdo->exec("UPDATE evaluation_cycles SET status = 'closed'");
            $stmt = $pdo->prepare("UPDATE evaluation_cycles SET status = 'active' WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->rowCount()) throw new RuntimeException('ไม่พบรอบการประเมิน');
            $pdo->commit();
            adminRedirect('cycles.php', 'success', 'เปิดรอบประเมินเรียบร้อย');
        }
        if ($action === 'close') {
            $id = requestInt($_POST['id'] ?? null, 'id');
            $stmt = $pdo->prepare("UPDATE evaluation_cycles SET status = 'closed' WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->rowCount()) throw new RuntimeException('ไม่พบรอบการประเมินที่เปิดอยู่');
            adminRedirect('cycles.php', 'success', 'ปิดรอบประเมินเรียบร้อย');
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        adminRedirect('cycles.php', 'error', $e->getMessage());
    }
}

$cycles = $pdo->query('SELECT c.*, COUNT(DISTINCT em.id) mapping_count, COUNT(DISTINCT e.id) evaluation_count FROM evaluation_cycles c LEFT JOIN evaluator_mapping em ON em.cycle_id=c.id LEFT JOIN evaluations e ON e.cycle_id=c.id GROUP BY c.id ORDER BY c.id DESC')->fetchAll();
require_once '../includes/header.php';
adminPageHeader(appIcon('calendar') . ' เปิด–ปิดรอบประเมิน', 'สร้างรอบประเมินและกำหนดรอบที่เปิดใช้งาน');
renderAdminFlash();
?>
<div class="card" style="margin-bottom:1.5rem">
  <h3>เพิ่มรอบประเมิน</h3>
  <form method="post" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;align-items:end">
    <?= adminCsrfField() ?>
    <input type="hidden" name="action" value="add">
    <div class="form-group" style="margin-bottom:0">
      <label for="fiscal_year">ปีงบประมาณ</label>
      <input class="form-control" type="text" id="fiscal_year" name="fiscal_year" required maxlength="4" inputmode="numeric" pattern="[0-9]{4}" placeholder="เช่น 2569">
    </div>
    <div class="form-group" style="margin-bottom:0">
      <label for="round_name">รอบที่</label>
      <select class="form-control" id="round_name" name="round_name" required>
        <option value="1">1</option>
        <option value="2">2</option>
      </select>
    </div>
    <button class="btn btn-primary" type="submit">+ เพิ่มรอบ</button>
    <small style="grid-column:1/-1;color:var(--text-muted)">ระบบกำหนดวันที่ให้อัตโนมัติ: รอบที่ 1 (1 ต.ค. – 31 มี.ค.) และรอบที่ 2 (1 เม.ย. – 30 ก.ย.)</small>
  </form>
</div>
<div class="card"><div style="overflow-x:auto"><table><thead><tr><th>ปีงบประมาณ</th><th>รอบ</th><th>ช่วงเวลา</th><th>ผู้รับมอบหมาย</th><th>แบบประเมิน</th><th>สถานะ</th><th>จัดการ</th></tr></thead><tbody>
<?php foreach ($cycles as $cycle): ?><tr>
<td><?= htmlspecialchars($cycle['fiscal_year']) ?></td><td><?= ctype_digit((string)$cycle['round_name']) ? 'รอบที่ ' . htmlspecialchars($cycle['round_name']) : htmlspecialchars($cycle['round_name']) ?></td>
<td><?= htmlspecialchars(formatThaiShortDate($cycle['start_date'])) ?> – <?= htmlspecialchars(formatThaiShortDate($cycle['end_date'])) ?></td>
<td><?= (int)$cycle['mapping_count'] ?></td><td><?= (int)$cycle['evaluation_count'] ?></td>
<td><span class="badge" style="background:<?= $cycle['status']==='active'?'#D1FAE5':'#E5E7EB' ?>;color:<?= $cycle['status']==='active'?'#065F46':'#374151' ?>"><?= $cycle['status']==='active'?'เปิดอยู่':'ปิดแล้ว' ?></span></td>
<td><form method="post"><?= adminCsrfField() ?><input type="hidden" name="id" value="<?= $cycle['id'] ?>"><input type="hidden" name="action" value="<?= $cycle['status']==='active'?'close':'open' ?>"><button class="btn <?= $cycle['status']==='active'?'btn-danger':'btn-success' ?>" type="submit"><?= $cycle['status']==='active'?'ปิดรอบ':'เปิดรอบ' ?></button></form></td>
</tr><?php endforeach; ?></tbody></table></div></div>
<?php require_once '../includes/footer.php'; ?>
