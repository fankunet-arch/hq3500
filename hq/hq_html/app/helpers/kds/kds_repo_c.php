<?php
/**
 * KDS Repo C - Dicts + Ops (Phase 2 consolidation)
 * V2 (2025-11-12):
 * - 全面加入 if (!function_exists(...)) 包裹，防止与 kds_repo_a/b 的函数重复定义导致 Fatal.
 * - 保留/包含三处修正：
 *   1) getAllTopupOrders：使用 topup_orders 别名 tpo、字段 topup_order_id/review_status
 *   2) getAllRedemptionBatches：使用 prb.created_at & prb.redeemed_uses，并聚合 pass_redemptions.extra_charge
 *   3) getSeasonsPassDashboardKpis：SUM(redeemed_uses) 与 review_status 统计口径
 */

/** ========== Ice / Sweet / Cup / Addon ========== */

if (!function_exists('getAllActiveIceOptions')) {
    function getAllActiveIceOptions(PDO $pdo): array {
        $sql = "SELECT i.id, i.ice_code, it_zh.ice_option_name AS name_zh, it_es.ice_option_name AS name_es, it_zh.sop_description AS sop_zh
                FROM kds_ice_options i
                LEFT JOIN kds_ice_option_translations it_zh ON i.id = it_zh.ice_option_id AND it_zh.language_code = 'zh-CN'
                LEFT JOIN kds_ice_option_translations it_es ON i.id = it_es.ice_option_id AND it_es.language_code = 'es-ES'
                WHERE i.deleted_at IS NULL
                ORDER BY i.ice_code ASC";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('getAllSweetnessOptions')) {
    function getAllSweetnessOptions(PDO $pdo): array {
        $sql = "
            SELECT 
                s.id, s.sweetness_code,
                st_zh.sweetness_option_name AS name_zh, 
                st_es.sweetness_option_name AS name_es,
                st_zh.sop_description AS sop_zh
            FROM kds_sweetness_options s 
            LEFT JOIN kds_sweetness_option_translations st_zh ON s.id = st_zh.sweetness_option_id AND st_zh.language_code = 'zh-CN' 
            LEFT JOIN kds_sweetness_option_translations st_es ON s.id = st_es.sweetness_option_id AND st_es.language_code = 'es-ES'
            WHERE s.deleted_at IS NULL 
            ORDER BY s.sweetness_code ASC";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('getAllCups')) {
    function getAllCups(PDO $pdo): array {
        $sql = "
            SELECT 
                c.id, c.cup_code, c.capacity_ml, c.is_active,
                ct_zh.cup_name AS name_zh,
                ct_es.cup_name AS name_es
            FROM kds_cups c
            LEFT JOIN kds_cup_translations ct_zh ON c.id = ct_zh.cup_id AND ct_zh.language_code = 'zh-CN'
            LEFT JOIN kds_cup_translations ct_es ON c.id = ct_es.cup_id AND ct_es.language_code = 'es-ES'
            WHERE c.deleted_at IS NULL
            ORDER BY c.cup_code ASC
        ";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('getAllAddons')) {
    function getAllAddons(PDO $pdo): array {
        $sql = "
            SELECT 
                a.id, a.addon_code, a.is_active,
                at_zh.addon_name AS name_zh,
                at_es.addon_name AS name_es
            FROM kds_addons a
            LEFT JOIN kds_addon_translations at_zh ON a.id = at_zh.addon_id AND at_zh.language_code = 'zh-CN'
            LEFT JOIN kds_addon_translations at_es ON a.id = at_es.addon_id AND at_es.language_code = 'es-ES'
            WHERE a.deleted_at IS NULL
            ORDER BY a.addon_code ASC
        ";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

/** ========== POS Tags ========== */

if (!function_exists('getAllPosTags')) {
    function getAllPosTags(PDO $pdo): array {
        $sql = "SELECT tag_id, tag_code, name_zh, name_es FROM pos_tags WHERE deleted_at IS NULL ORDER BY tag_code ASC";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('getTagById')) {
    function getTagById(PDO $pdo, int $tag_id): ?array {
        $sql = "SELECT tag_id, tag_code, name_zh, name_es FROM pos_tags WHERE tag_id = ? AND deleted_at IS NULL";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tag_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('getProductTagIds')) {
    function getProductTagIds(PDO $pdo, int $menu_item_id): array {
        $sql = "SELECT tag_id FROM pos_menu_item_tags WHERE menu_item_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$menu_item_id]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
}

if (!function_exists('getAddonTagIds')) {
    function getAddonTagIds(PDO $pdo, int $addon_id): array {
        $sql = "SELECT tag_id FROM pos_addon_tags WHERE addon_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$addon_id]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
}

/** ========== Materials & Units（与 repo_a 可能重叠，故加防重复） ========== */

if (!function_exists('getAllUnits')) {
    function getAllUnits(PDO $pdo): array {
        $sql = "
            SELECT 
                u.id, u.unit_code,
                u.base_multiplier, u.base_unit_id,
                ut_zh.unit_name AS name_zh,
                ut_es.unit_name AS name_es
            FROM kds_units u
            LEFT JOIN kds_unit_translations ut_zh ON u.id = ut_zh.unit_id AND ut_zh.language_code = 'zh-CN'
            LEFT JOIN kds_unit_translations ut_es ON u.id = ut_es.unit_id AND ut_es.language_code = 'es-ES'
            WHERE u.deleted_at IS NULL
            ORDER BY u.unit_code ASC
        ";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('getAllMaterials')) {
    function getAllMaterials(PDO $pdo): array {
        $sql = "
            SELECT 
                m.id, m.material_code, m.material_name, m.category_id, m.default_unit_id,
                m.density, m.loss_rate, m.image_url
            FROM kds_materials m
            WHERE m.deleted_at IS NULL
            ORDER BY m.material_code ASC, m.id ASC
        ";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('getMaterialById')) {
    function getMaterialById(PDO $pdo, int $id): ?array {
        $sql = "
            SELECT 
                m.id, m.material_code, m.material_name, m.category_id, m.default_unit_id,
                m.density, m.loss_rate, m.image_url
            FROM kds_materials m
            WHERE m.id = ?
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

/** ========== Products（与 repo_a 可能重叠，故加防重复） ========== */

if (!function_exists('getAllBaseProducts')) {
    function getAllBaseProducts(PDO $pdo): array {
        $sql = "
            SELECT
                p.id,
                p.product_code,
                tzh.product_name AS name_zh,
                tes.product_name AS name_es
            FROM kds_products p
            LEFT JOIN kds_product_translations tzh ON tzh.product_id = p.id AND tzh.language_code = 'zh-CN'
            LEFT JOIN kds_product_translations tes ON tes.product_id = p.id AND tes.language_code = 'es-ES'
            WHERE p.deleted_at IS NULL
            ORDER BY p.product_code ASC, p.id ASC
        ";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

/** ========== Status（与 repo_a 可能重叠，故加防重复） ========== */

if (!function_exists('getAllStatuses')) {
    function getAllStatuses(PDO $pdo): array {
        $sql = "
            SELECT status_code, name_zh, name_es
            FROM kds_statuses
            WHERE deleted_at IS NULL
            ORDER BY sort_order ASC, status_code ASC
        ";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

/** ========== Member & Pass ========== */

if (!function_exists('getMemberByPhone')) {
    function getMemberByPhone(PDO $pdo, string $phone): ?array {
        $sql = "SELECT id, member_uuid, phone_number, nickname, created_at FROM pos_members WHERE phone_number = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$phone]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('getPassPlanById')) {
    function getPassPlanById(PDO $pdo, int $pass_plan_id): ?array {
        $sql = "SELECT pass_plan_id, name, total_uses, validity_days, allocation_strategy FROM pass_plans WHERE pass_plan_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$pass_plan_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('getActiveMemberPass')) {
    function getActiveMemberPass(PDO $pdo, int $member_id): ?array {
        $sql = "
            SELECT 
                member_pass_id, member_id, pass_plan_id, remaining_uses, status, created_at, expires_at
            FROM member_passes
            WHERE member_id = ? AND status = 'ACTIVE'
            ORDER BY created_at DESC
            LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$member_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

/** ========== Topup Orders（修正版） ========== */

if (!function_exists('getAllTopupOrders')) {
    function getAllTopupOrders(PDO $pdo): array {
        try {
            $sql = "
                SELECT 
                    tpo.topup_order_id,
                    tpo.review_status,
                    tpo.sale_time,
                    tpo.amount_total,
                    tpo.quantity,
                    tpo.reviewed_at,
                    tpo.voucher_series,
                    tpo.voucher_number,
                    pm.phone_number AS member_phone,
                    pp.name AS plan_name,
                    ks.store_name,
                    cu.display_name AS reviewer_name
                FROM topup_orders tpo
                JOIN pos_members pm ON tpo.member_id = pm.id
                JOIN pass_plans pp ON tpo.pass_plan_id = pp.pass_plan_id
                JOIN kds_stores ks ON tpo.store_id = ks.id
                LEFT JOIN cpsys_users cu ON tpo.reviewed_by_user_id = cu.id
                ORDER BY tpo.sale_time DESC
            ";
            $stmt = $pdo->query($sql);
            return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (PDOException $e) {
            error_log("Error in getAllTopupOrders: " . $e->getMessage());
            return [];
        }
    }
}

/**
 * 审批并激活会员次卡（保持最小改动，字段对齐 topup_orders）
 * 返回：true | 错误字符串
 */
if (!function_exists('activate_member_pass')) {
    function activate_member_pass(PDO $pdo, int $topup_order_id, int $reviewer_id, string $now_utc_str) {
        try {
            // 1) 读取订单 & 方案
            $sql_order = "
                SELECT tpo.*, pp.total_uses, pp.validity_days
                FROM topup_orders tpo
                JOIN pass_plans pp ON tpo.pass_plan_id = pp.pass_plan_id
                WHERE tpo.topup_order_id = ?
            ";
            $stmt = $pdo->prepare($sql_order);
            $stmt->execute([$topup_order_id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$order) return "Topup order not found: {$topup_order_id}";

            $pdo->beginTransaction();

            // 2) 写入/累加 member_passes
            $quantity = (int)$order['quantity'];
            $uses_add = $quantity * (int)$order['total_uses'];
            $sql_upsert = "
                INSERT INTO member_passes (member_id, pass_plan_id, remaining_uses, status, created_at, expires_at)
                VALUES (?, ?, ?, 'ACTIVE', ?, DATE_ADD(?, INTERVAL ? DAY))
                ON DUPLICATE KEY UPDATE 
                    remaining_uses = remaining_uses + VALUES(remaining_uses),
                    status = 'ACTIVE',
                    expires_at = GREATEST(expires_at, VALUES(expires_at))
            ";
            $stmt2 = $pdo->prepare($sql_upsert);
            $stmt2->execute([
                $order['member_id'],
                $order['pass_plan_id'],
                $uses_add,
                $now_utc_str,
                $now_utc_str,
                (int)$order['validity_days']
            ]);

            // 3) 更新订单审核状态（最小改动：直接置为 approved）
            $sql_upd = "
                UPDATE topup_orders
                SET review_status = 'approved',
                    reviewed_by_user_id = ?,
                    reviewed_at = ?
                WHERE topup_order_id = ?
            ";
            $stmt3 = $pdo->prepare($sql_upd);
            $stmt3->execute([$reviewer_id, $now_utc_str, $topup_order_id]);

            $pdo->commit();
            return true;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log("[activate_member_pass] " . $e->getMessage());
            return $e->getMessage();
        }
    }
}

/** ========== Redemption Batches（修正版） ========== */

if (!function_exists('getAllRedemptionBatches')) {
    function getAllRedemptionBatches(PDO $pdo): array {
        try {
            $sql = "
                SELECT 
                    prb.batch_id,
                    prb.created_at AS batch_created_at,
                    prb.redeemed_uses,
                    COALESCE(pr_agg.extra_charge_total, 0) AS extra_charge_total,
                    pm.phone_number AS member_phone,
                    pp.name AS plan_name,
                    ks.store_name,
                    pi.series AS invoice_series,
                    pi.number AS invoice_number
                FROM pass_redemption_batches prb
                JOIN member_passes mp ON prb.member_pass_id = mp.member_pass_id
                JOIN pos_members pm   ON mp.member_id = pm.id
                JOIN pass_plans  pp   ON mp.pass_plan_id = pp.pass_plan_id
                JOIN kds_stores  ks   ON prb.store_id = ks.id
                LEFT JOIN pos_invoices pi ON prb.order_id = pi.id
                LEFT JOIN (
                    SELECT batch_id, SUM(extra_charge) AS extra_charge_total
                    FROM pass_redemptions
                    GROUP BY batch_id
                ) pr_agg ON pr_agg.batch_id = prb.batch_id
                ORDER BY prb.created_at DESC
            ";
            $stmt = $pdo->query($sql);
            return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (PDOException $e) {
            error_log("Error in getAllRedemptionBatches: " . $e->getMessage());
            return [];
        }
    }
}

/** ========== Dashboard KPIs（修正版） ========== */

if (!function_exists('getSeasonsPassDashboardKpis')) {
    function getSeasonsPassDashboardKpis(PDO $pdo): array {
        $now_utc_str = utc_now()->format('Y-m-d H:i:s');

        // 1) 活跃次卡
        $stmt_active = $pdo->prepare("
            SELECT COUNT(member_pass_id) 
            FROM member_passes 
            WHERE status = 'ACTIVE' 
              AND remaining_uses > 0
              AND (expires_at IS NULL OR expires_at > ?)
        ");
        $stmt_active->execute([$now_utc_str]);
        $active_passes_count = (int)$stmt_active->fetchColumn();

        // 2) 累计核销次数（按批次求和）
        $stmt_red = $pdo->query("SELECT COALESCE(SUM(redeemed_uses), 0) FROM pass_redemption_batches");
        $total_redemptions_count = (int)($stmt_red ? $stmt_red->fetchColumn() : 0);

        // 3) 累计售卡金额（使用 review_status 口径）
        $stmt_sales = $pdo->query("
            SELECT COALESCE(SUM(amount_total), 0) 
            FROM topup_orders 
            WHERE review_status IN ('confirmed','approved')
        ");
        $total_sales_amount = (float)($stmt_sales ? $stmt_sales->fetchColumn() : 0);

        return [
            'active_passes_count'     => $active_passes_count,
            'total_redemptions_count' => $total_redemptions_count,
            'total_sales_amount'      => $total_sales_amount
        ];
    }
}

/** ========== Dashboard 示例 ========== */

if (!function_exists('getTodayTopItems')) {
    function getTodayTopItems(PDO $pdo, string $tzLocal = 'Europe/Madrid'): array {
        $tz = new DateTimeZone($tzLocal);
        $todayLocal = (new DateTimeImmutable('now', $tz))->format('Y-m-d');
        $start_local = new DateTimeImmutable($todayLocal . ' 00:00:00', $tz);
        $end_local   = new DateTimeImmutable($todayLocal . ' 23:59:59', $tz);
        $utc = new DateTimeZone('UTC');
        $start = $start_local->setTimezone($utc)->format('Y-m-d H:i:s');
        $end   = $end_local->setTimezone($utc)->format('Y-m-d H:i:s');

        $sql = "
            SELECT 
                pi.item_name_zh,
                SUM(pi.quantity) AS total_quantity
            FROM pos_invoice_items pi
            JOIN pos_invoices p ON pi.invoice_id = p.id
            WHERE p.issued_at BETWEEN :start AND :end AND p.status = 'ISSUED'
            GROUP BY pi.item_name_zh
            ORDER BY total_quantity DESC
            LIMIT 5
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':start' => $start, ':end' => $end]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
