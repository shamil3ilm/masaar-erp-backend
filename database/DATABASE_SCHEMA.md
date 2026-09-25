# Database Schema

Generated from `database/migrations` by `php artisan schema:doc`. Do not edit
this file by hand: change a migration, then run the command again. Each
column shows the Blueprint call and modifiers the migration wrote.

978 tables across 51 migrations.

## Contents

- `0010_accounting.php`: check_register_entries, cost_reconciliation_runs, cost_reconciliation_entries, cost_splitting_results, currencies, direct_debit_collections, fx_forwards, fx_hedge_relations, fx_valuations, intercompany_reconciliation_matches, intercompany_reconciliation_sessions, intercompany_reconciliation_items, material_ledger_closing_entries, material_ledger_documents, material_ledger_price_differences, profitability_segment_values, statistical_key_figure_values, variance_analysis_items
- `0020_admin.php`: platform_admin_roles, platform_admins, admin_ip_whitelist, admin_notifications, feature_flags, platform_admin_sessions, platform_permissions, platform_settings, system_announcements, dim_time, discount_codes, subscription_addons, subscription_plans, metered_pricing_tiers, budget_transfers
- `0030_core.php`: business_partners, business_partner_roles, class_assignments, class_characteristic_values, class_characteristics, dashboard_widgets, edi_message_segments, edi_messages, failed_jobs_monitor, languages, module_definitions, module_readiness_checks, organizations, api_call_logs, approval_workflows, approval_workflow_steps, branches, classification_classes, custom_field_definitions, custom_field_groups, custom_field_values, edi_partners, email_templates, gdpr_processing_activities, import_templates, number_sequences, onboarding_templates, onboarding_steps, organization_branding, permissions, print_configurations, print_templates, retention_policies, retention_schedule_runs, roles, role_menu_items, role_module_permissions, sso_providers, tenant_rate_limit_configs, tracked_jobs
- `0040_core_2.php`: translations, users, activities, activity_logs, approval_requests, approval_actions, attachments, attachment_access_logs, bulk_operation_jobs, change_freeze_periods, change_transport_requests, change_transport_logs, change_transport_object_assignments, change_transport_objects, comments, dashboard_layouts, document_download_tokens, document_legal_holds, email_logs, entity_views, export_jobs, feature_adoption_events, organization_feature_flags, feature_flag_rollout_logs, feature_flag_targets, gdpr_data_subject_requests, import_jobs, ip_allowlist_rules, job_monitors, job_monitor_logs, login_history, mentions, module_access_logs, module_readiness_results, notification_preferences, notifications, organization_module_access, organization_modules, recurring_profiles, recurring_profile_logs, sensitive_access_logs
- `0050_core_3.php`: sso_sessions, user_events, user_module_overrides, user_onboarding_progress, user_preferences, user_sessions, webhook_events, webhooks, webhook_deliveries, webhook_dlq_entries, workflow_escalation_logs, workflow_escalation_rules, workflow_substitution_rules
- `0060_accounting_2.php`: accounting_account_groups, accounting_document_types, bank_guarantees, cash_flow_scenarios, cash_flow_forecasts, cash_flow_forecast_lines, cash_flow_lines, chart_of_accounts, account_balance_snapshots, accrual_deferrals, asset_categories, bank_accounts, bank_account_requests, bank_matching_rules, bank_positions, bank_reconciliations, bank_signatories, bank_statement_imports, bank_transactions, bank_reconciliation_items, check_books, distribution_cycles, collections_worklist, consolidation_groups, consolidation_entities, copa_dimensions, cost_elements, activity_types, cost_repostings, cost_splitting_rules, costing_sheets, costing_sheet_runs, direct_debit_mandates, dispute_cases, document_splitting_rules, dunning_levels, dunning_runs, exchange_rates, financial_close_templates, financial_close_periods
- `0070_accounting_3.php`: financial_close_template_tasks, financial_close_tasks, financial_statement_versions, financial_statement_version_nodes, fiscal_years, account_opening_balances, accounting_periods, carry_forward_runs, consolidation_periods, consolidated_balances, copa_plan_versions, depreciation_runs, fixed_assets, house_banks, house_bank_accounts, installment_plans, journal_entries, asset_components, asset_transactions, asset_transfers, currency_revaluations, depreciation_run_lines, elimination_entries, forex_gain_loss_entries, installment_schedules, lease_contracts, lease_schedules, liquidity_plans, liquidity_plan_lines, material_ledger_records, organization_currencies, overhead_keys, parked_documents, payment_advices, payment_runs, payment_files, payment_run_items, payment_terms, payment_tolerance_groups, payment_difference_posts
- `0080_accounting_4.php`: payment_tolerance_items, period_lock_overrides, posting_validation_rules, profitability_segments, assessment_cycles, special_ledgers, special_ledger_entries, special_ledger_mapping_rules, statistical_key_figures, transfer_price_versions, treasury_investments, variance_analysis_runs, withholding_tax_codes, withholding_tax_lines, xbrl_taxonomies, xbrl_filings, xbrl_filing_elements, zakat_assessments
- `0090_admin_2.php`: announcement_reads, organization_admin_notes, organization_status_history, platform_admin_activities, support_tickets, support_ticket_messages, dim_organization, user_activity_logs, user_cluster_assignments, user_feature_usage, user_sessions_extended, automation_email_templates, automation_rules, automation_rule_logs, automation_schedules
- `0100_billing.php`: api_request_logs, billing_credits, billing_payment_methods, organization_subscriptions, billing_invoices, billing_invoice_items, billing_payments, billing_credit_transactions, discount_code_usages, subscription_addon_purchases, usage_aggregates, usage_alerts, usage_metrics, usage_snapshots, budgets, budget_revisions
- `0110_calendar.php`: calendars, calendar_events, calendar_event_attendees, calendar_event_reminders, calendar_recurring_rules, reminders, user_segments, campaigns, campaign_sends
- `0120_compliance.php`: bahrain_vat_returns, dps_sanction_lists, dps_list_entries, dps_screening_runs, dps_screening_results, uae_cit_assessments
- `0130_crm.php`: crm_activities, lead_sources, pipeline_stages, sla_policies, territories, territory_routing_rules
- `0140_document.php`: document_folders, documents, digital_signatures, document_activities, document_permissions, document_shares, document_versions, payment_gateways, expense_categories, petty_cash_funds, petty_cash_replenishments, petty_cash_vouchers, petty_cash_transactions, fraud_rules
- `0150_hr.php`: appraisal_cycles, appraisal_templates, appraisal_template_sections, appraisal_template_questions, benefit_types, candidates, compensation_reviews, departments, designations, employee_exits, employees, compensation_review_items, employee_benefits, benefit_changes, employee_dependents, employee_documents, employee_experiences, employee_loans, employee_qualifications, eosb_policies, eosb_calculations, eosb_provisions, eosb_settlements, exit_clearance_items, gosi_configurations, gosi_contributions, holidays, hr_onboardings, hr_onboarding_tasks, job_postings, job_applications, interview_schedules, job_offers, key_positions, leave_policies, leave_types, leave_balances, leave_accruals, leave_adjustments, leave_requests
- `0160_hr_2.php`: leave_calendar, leave_tiers, leave_tier_approvers, manager_delegations, manager_team_views, off_cycle_payroll_items, off_cycle_payroll_runs, om_tasks, overtime_policies, overtime_requests, pay_grades, payroll_corrections, payroll_periods, epf_contributions, esi_contributions, leave_encashments, per_diem_rates, performance_appraisals, appraisal_responses, appraisal_reviewers, appraisal_reviewer_responses, performance_goals, performance_goal_updates, personnel_actions, personnel_action_steps, positions, employee_transfers, om_position_tasks, probation_periods, professional_tax_configs, public_holidays, salary_components, salary_structures, employee_salaries, employee_salary_components, payslips, loan_repayments, payslip_items, salary_structure_components, shift_patterns
- `0170_hr_3.php`: shift_rosters, shift_roster_lines, shift_swap_requests, shifts, employee_shift_assignments, skill_categories, skills, social_insurance_schemes, social_insurance_records, social_insurance_submissions, social_insurance_submission_lines, succession_candidates, succession_plans, succession_plan_candidates, succession_pool_activities, time_sheets, time_wage_types, time_evaluation_results, training_providers, training_courses, training_needs, training_sessions, training_enrollments, training_certifications, travel_expense_types, travel_requests, travel_expense_claims, travel_expense_lines, travel_expense_reports, travel_expense_report_lines, work_schedules, attendances
- `0180_accounting_5.php`: cost_centers, activity_rates, assessment_postings, distribution_postings, distribution_segments, cost_allocations, cost_center_budgets, cost_center_budget_lines, cost_center_budget_supplements, costing_sheet_rows, costing_sheet_run_results, internal_orders, internal_order_settlements, overhead_key_rates, profit_centers, assessment_cycle_segments, assessment_cycle_receivers, distribution_segment_receivers, copa_line_items, copa_planned_line_items, cost_center_assignments, journal_entry_split_items, profit_center_plans, recurring_journal_templates, budget_lines, budget_commitments, budget_revision_lines, territory_assignments, expense_budgets, expense_reports, org_units, time_sheet_entries
- `0190_inventory.php`: batch_classes, batch_characteristics, categories, hazmat_classifications, hazmat_storage_classes, hazmat_storage_compatibility_rules, inventory_split_valuations, inventory_valuation_categories, inventory_valuation_types, qr_code_configs, storage_type_determination_rules, storage_types, units_of_measure, warehouses, cross_docking_orders, cycle_count_plans, cycle_count_sessions, ewm_storage_types, ewm_putaway_rules, ewm_storage_sections, ewm_bins, goods_issues, physical_inventory_documents, stock_adjustments, stock_transfers, warehouse_locations, warehouse_transfer_orders, wave_plans, picking_lists, wave_plan_orders, yard_zones, dock_doors, dim_warehouse
- `0200_maintenance.php`: equipment_categories, functional_locations, equipment, maintenance_condition_rules, maintenance_fault_codes, maintenance_kpis, maintenance_measurements, maintenance_notifications, maintenance_notification_items, maintenance_notification_tasks, maintenance_order_settlements, maintenance_plans, maintenance_orders, maintenance_order_tasks, maintenance_permits, maintenance_root_cause_analyses, maintenance_service_orders, maintenance_task_lists, permit_safety_checks, vehicles, fuel_logs, mileage_logs, vehicle_assignments, vehicle_maintenance_records
- `0210_manufacturing.php`: audit_plans, audit_checklists, audit_reports, bom_alternatives, bom_co_products, capa_records, capa_actions, capa_effectiveness_reviews, cost_versions, cost_rollup_logs, costing_versions, costing_runs, engineering_change_objects, engineering_changes, kanban_supply_areas, mrp_runs, planning_simulations, product_cost_collector_items, product_cost_collectors, production_resource_tools, tool_operation_assignments, capa_8d, dynamic_modification_rules, inspection_stage_logs, scheduling_boards, scrap_reports, skip_lot_sampling_plans, spc_charts, spc_subgroups, work_centers, capacity_loads, planning_capacity_requirements, mrp_capacity_requirements, production_lines, work_center_capacities, work_center_exceptions, work_order_co_product_actuals, audit_findings, task_list_operations
- `0220_messaging.php`: conversations, conversation_messages, message_templates, channel_template_approvals, messaging_channels, messaging_automations, message_campaign_recipients
- `0240_purchase.php`: ers_runs, pcards, pcard_statements, pcard_transactions, purchase_requisitions, release_strategies, release_strategy_levels, release_strategy_approvals, rfq_headers, supplier_evaluation_criteria
- `0250_realestate.php`: occupancy_snapshots, portfolios, posting_runs, properties, buildings, floors, rental_units, rental_contracts, contract_conditions, contract_options, ifrs16_schedules, posting_run_items, security_deposits, service_charge_settlements, service_charge_allocations, service_charge_items, vacancy_periods
- `0260_sales.php`: backdated_transactions, backorder_records, billing_plan_items, billing_plans, bulk_sale_batches, commission_masters, commission_payments, commission_rules, customer_account_groups, customer_groups, delivery_modes, delivery_split_rules, delivery_zones, delivery_zone_rates, handling_unit_items, handling_units, material_account_groups, output_types, output_condition_records, output_messages, payment_modes, price_lists, contacts, consignment_orders, customer_credits, payments_received, price_list_assignments, price_override_policies, price_override_reasons, pricing_condition_types, pricing_condition_records, pricing_procedures, product_attributes, product_bundles, product_tags, promotions, coupon_codes, promotion_usages, quick_sale_templates, quotations
- `0270_sales_2.php`: rebate_masters, return_policies, return_reasons, revenue_account_determination_keys, revenue_contracts, performance_obligations, revenue_recognition_events, sales_order_cost_estimate_items, sales_order_cost_estimates, sales_orders, delivery_documents, intercompany_sales_orders, intercompany_billing_documents, invoices, cash_sales, commission_calculations, payment_allocations, pick_documents, rebate_accruals, seasonal_campaigns, campaign_tier_offers, shipments, shipment_tracking_events, shipping_route_determinations, shipping_routes, shipping_zones, third_party_order_lines, third_party_orders, wallets, wallet_transactions, advance_payments, advance_payment_applications
- `0280_accounting_6.php`: credit_exposures, credit_holds, credit_limits, currency_revaluation_items, dunning_blocks, dunning_notices, dunning_notice_items, journal_entry_lines, loans, inter_company_transfers, loan_schedules, loan_payments, aml_cdd_records, aml_risk_scores, aml_screening_cache, aml_suspicious_activities, aml_transaction_flags, dim_customer, dim_vendor, gdpr_consent_records, portal_users, portal_activity_logs, portal_document_accesses, portal_sessions, leads, opportunities, service_tickets, service_ticket_comments, ecommerce_channels, ecommerce_orders, ecommerce_sync_logs, invoice_qr_codes, online_payments, recurring_expenses, fraud_alerts, price_check_stations, truck_appointments, yard_movements
- `0290_loyalty.php`: maintenance_order_cost_lines, complaints, complaint_communications, complaint_resolutions, supplier_quality_ratings, contact_messaging_preferences, outbound_messages, message_logs, message_queues, outbound_message_attachments
- `0310_purchase_2.php`: contracts, contract_documents, contract_milestones, contract_releases, ers_configurations, outline_agreements, payments_made, purchase_orders, bills, bill_payment_allocations, procurement_goods_receipts, rfq_vendors, rfq_quotes, service_purchase_orders, service_entry_sheets, service_acceptances, service_po_lines, service_entry_sheet_lines, supplier_credits, supplier_delivery_records, supplier_incidents, supplier_scorecards, supplier_scorecard_ratings, vendor_advance_requests, vendor_advance_payments, vendor_advance_clearings, vendor_advances, vendor_advance_adjustments, vendor_consignment_settlements, vendor_contracts, vendor_credit_notes, expenses, expense_items, expense_receipts, expense_report_items, subcontract_orders, subcontract_receipts
- `0320_sales_3.php`: credit_notes, credit_note_applications, debit_notes, intercompany_purchase_order_links, purchase_returns, sales_returns, exchange_orders, refunds, rma_requests, audit_logs, settings
- `0340_tax.php`: hsn_sac_codes, tax_categories, tax_rates, tax_determination_rules, vat_return_periods, vat_return_boxes, vat_transactions
- `0350_inventory_2.php`: products, cross_docking_order_lines, cycle_count_lines, ewm_transfer_orders, ewm_labor_tasks, hazmat_transport_regulations, product_certifications, product_documents, product_hazmat_classifications, product_relations, product_reviews, product_specifications, product_variants, inventory_batches, batch_characteristic_values, batch_where_used_records, goods_issue_lines, physical_inventory_lines, picking_list_lines, price_check_logs, product_barcodes, product_images, product_price_history, product_videos, putaway_rules, safety_data_sheets, safety_data_sheet_sections, serial_numbers, serial_number_movements, shelf_labels, stock_adjustment_lines, stock_level_snapshots, stock_levels, stock_movements, stock_transfer_lines, warehouse_transfer_order_items, transfer_prices, transfer_price_conditions, transfer_price_history
- `0360_analytics.php`: dim_product, fact_inventory_movements, fact_purchases, fact_sales, ecommerce_order_items, ecommerce_product_mappings, equipment_spare_parts, maintenance_order_parts
- `0370_manufacturing_2.php`: approved_vendor_lists, bom_templates, bom_lines, bom_operations, certificates_of_analysis, demand_forecasts, kanban_control_cycles, kanban_cards, mrp_demand_items, mrp_planned_orders, planned_independent_requirements, product_costs, product_standard_costs, cost_components, quality_cost_entries, quality_notifications, defect_records, quality_plans, inspection_lot_configs, inspection_lots, procurement_inspection_configs, procurement_inspections, procurement_inspection_results, q_info_records, quality_plan_characteristics, inspection_results, recipes, recipe_phases, recipe_resources, returns_inspection_lots, returns_inspection_defects, routing_headers, production_versions, long_term_planned_orders, process_orders, process_order_phases, process_order_resources, repetitive_mfg_schedules, repetitive_mfg_backflushes, repetitive_mfg_schedule_lines
- `0380_manufacturing_3.php`: routing_operations, skip_lot_decisions, stability_studies, stability_study_time_points, stability_study_results, subcontract_components, subcontract_order_lines, subcontract_receipt_lines, subcontract_transfers, subcontract_transfer_lines, supplier_ncr_records, usage_decisions
- `0390_purchase_3.php`: bill_lines, contract_lines, goods_receipts, ers_run_items, outline_agreement_items, outline_agreement_releases, purchase_order_lines, goods_receipt_lines, procurement_gr_lines, purchase_requisition_lines, purchasing_info_records, purchasing_info_record_conditions, quota_arrangements, quota_arrangement_items, rfq_items, rfq_lines, rfq_quote_lines, rfq_vendor_quotes, scheduling_agreements, sa_delivery_schedules, three_way_match_results, vendor_consignment_stocks, vendor_consignment_receipts, vendor_consignment_withdrawals, vendor_contract_items, vendor_credit_note_lines, vendor_product_pricing, vendor_source_lists
- `0400_sales_4.php`: atp_checks, bulk_sale_items, cash_sale_lines, consignment_order_lines, consignment_stocks, consignment_movements, cpq_configurable_products, cpq_configurations, cpq_option_groups, cpq_options, cpq_configuration_items, cpq_constraint_rules, cpq_pricing_rules, customer_material_infos, debit_note_items, exchange_order_items, free_goods_conditions, intercompany_sales_order_lines, invoice_lines, credit_note_items, price_list_items, price_overrides, price_volume_breaks, product_attribute_values, product_bundle_items, product_tag_assignments, purchase_return_items, quotation_lines, rma_items, sales_order_lines, delivery_document_lines, pick_document_lines, sales_return_items, shipment_items
- `0410_manufacturing_4.php`: work_orders, capacity_requirements, cost_variances, production_logs, production_variances, scheduling_operations, scheduling_pegging_relationships, wip_valuations, work_order_materials, material_transactions, work_order_operations, activity_confirmations
- `0420_tm.php`: carriers, carrier_performance, carrier_services, freight_rate_tables, freight_agreements, freight_rate_lines, freight_surcharges, freight_tender_requests, freight_tender_bids, freight_tender_items, load_plans, transportation_orders, load_plan_items, transportation_order_items
- `0440_shared.php`: audit_log_archives, cache, cache_locks, calibration_equipment, capacity_slots, competency_frameworks, competency_framework_skills, conversation_participants, daily_work_schedules, failed_jobs, financial_close_task_dependencies, financial_idempotency_keys, location_equipment, hr_budget_plans, idempotency_keys, invoice_archives, job_batches, jobs, journal_entry_archives
- `0450_shared_2.php`: login_attempts, org_positions, password_changes, password_reset_tokens, payroll_schemas, period_work_schedules, production_confirmations, promotion_customers, promotion_products, report_definitions, role_permissions, scheduling_runs, sessions, shop_floor_papers, staging_requests, staging_request_lines, technical_completion_records, token_blacklist, user_branches, user_roles, user_segment_memberships, wage_type_catalog
- `0460_shared_3.php`: work_schedule_rules, equipment_counters, counter_based_plans, counter_based_orders, counter_readings, calibration_plans, calibration_orders, calibration_certificates, saved_reports, report_executions
- `0470_deferred_keys.php`: changes to leads, sales_returns, leave_balances
- `0480_mysql_indexes.php`: changes to product_attribute_values, failed_jobs_monitor
- `0490_goods_receipt_inspection_status.php`: changes to goods_receipts
- `0500_stock_movement_material_types.php`: changes to stock_movements
- `0510_recurring_profile_log_created_nullable.php`: changes to recurring_profile_logs
- `0520_continue_number_sequences_after_stored_numbers.php`: data only, no schema changes
- `0530_purchase_order_pending_approval_status.php`: changes to purchase_orders
- `0540_messaging_channel_default_marker.php`: changes to messaging_channels
- `0550_organization_parent.php`: changes to organizations

## 0010_accounting.php

### check_register_entries

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), name('cre_org_fk') |
| check_book_id | `foreignId` | nullable, constrained('check_books'), name('cre_checkbook_fk') |
| check_number | `string(20)` |  |
| check_type | `enum(['payment', 'payroll', 'refund', 'other'])` | default('payment') |
| direction | `enum(['issued', 'received'])` | default('issued') |
| payee_id | `foreignId` | nullable, constrained('contacts'), name('cre_payee_fk') |
| payment_made_id | `foreignId` | nullable, constrained('payments_made'), name('cre_payment_made_fk') |
| payment_received_id | `foreignId` | nullable, constrained('payments_received'), name('cre_payment_rcvd_fk') |
| check_date | `date` |  |
| amount | `decimal(18, 4)` |  |
| currency_code | `char(3)` | default('SAR') |
| memo | `text` | nullable |
| status | `enum(['draft', 'printed', 'issued', 'presented', 'cleared', 'bounced', 'cancelled', 'stale'])` | default('draft') |
| printed_at | `dateTime` | nullable |
| issued_at | `dateTime` | nullable |
| cleared_at | `dateTime` | nullable |
| bounced_at | `dateTime` | nullable |
| bounce_reason | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'check_number', 'direction'], 'cre_org_num_dir_unq')`
- `$table->index(['organization_id', 'status'], 'cre_org_status_idx')`
- `$table->index(['check_date'], 'cre_check_date_idx')`

### cost_reconciliation_runs

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| run_number | `string` | unique — KALC-2026-001 |
| source_type | `string` | assessment\|distribution\|activity_confirmation |
| source_id | `unsignedBigInteger` | FK to the CO posting (polymorphic by source_type) |
| fiscal_year | `string(4)` |  |
| period | `string(2)` |  |
| status | `string` | default('pending') — pending\|posted\|reversed |
| total_amount | `decimal(15, 2)` | default(0) |
| currency | `string(3)` | default('SAR') |
| posted_by | `unsignedBigInteger` | nullable |
| posted_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'status'])`
- `$table->index(['source_type', 'source_id'])`

### cost_reconciliation_entries

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| reconciliation_run_id | `unsignedBigInteger` |  |
| entry_type | `string` | debit\|credit |
| sender_company_id | `unsignedBigInteger` |  |
| receiver_company_id | `unsignedBigInteger` |  |
| sender_cost_center_id | `unsignedBigInteger` |  |
| receiver_cost_center_id | `unsignedBigInteger` |  |
| cost_element_id | `unsignedBigInteger` |  |
| journal_entry_id | `unsignedBigInteger` | nullable — generated FI document |
| amount | `decimal(15, 2)` |  |
| currency | `string(3)` | default('SAR') |
| description | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'reconciliation_run_id'], 'co_recon_entries_org_run_idx')`

Foreign keys:

- `$table->foreign('reconciliation_run_id')->references('id')->on('cost_reconciliation_runs')->cascadeOnDelete()`

### cost_splitting_results

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), name('csres_org_fk') |
| cost_splitting_rule_id | `foreignId` | constrained('cost_splitting_rules'), name('csres_rule_fk') |
| period | `unsignedTinyInteger` |  |
| fiscal_year | `unsignedSmallInteger` |  |
| total_cost | `decimal(18, 4)` |  |
| fixed_cost | `decimal(18, 4)` |  |
| variable_cost | `decimal(18, 4)` |  |
| run_at | `dateTime` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'period', 'fiscal_year'], 'csres_org_period_fy_idx')`

### currencies

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| code | `string(3)` | unique — ISO 4217 code |
| name | `string(50)` |  |
| symbol | `string(10)` |  |
| decimal_places | `unsignedTinyInteger` | default(2) |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

### direct_debit_collections

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), name('ddc_org_fk') |
| direct_debit_mandate_id | `foreignId` | constrained('direct_debit_mandates'), name('ddc_mandate_fk') |
| collection_date | `date` |  |
| amount | `decimal(18, 4)` |  |
| status | `enum(['scheduled', 'submitted', 'collected', 'failed', 'returned'])` | default('scheduled') |
| payment_run_id | `foreignId` | nullable, constrained('payment_runs'), name('ddc_payment_run_fk') |
| failure_reason | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['direct_debit_mandate_id'], 'ddc_mandate_idx')`
- `$table->index(['collection_date', 'status'], 'ddc_date_status_idx')`

### fx_forwards

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| contract_number | `string(50)` | unique |
| counterparty_bank | `string` | nullable |
| buy_currency | `string(3)` |  |
| sell_currency | `string(3)` |  |
| notional_amount | `decimal(18, 4)` | amount in buy_currency |
| forward_rate | `decimal(18, 8)` | agreed rate |
| trade_date | `date` |  |
| maturity_date | `date` |  |
| purpose | `enum(['speculative', 'hedge'])` | default('hedge') |
| status | `enum(['active', 'matured', 'cancelled', 'exercised'])` | default('active') |
| settlement_rate | `decimal(18, 8)` | nullable |
| settlement_gain_loss | `decimal(18, 4)` | nullable |
| settled_at | `date` | nullable |
| derivative_asset_account_id | `unsignedBigInteger` | nullable |
| unrealised_gain_loss_account_id | `unsignedBigInteger` | nullable |
| realised_gain_loss_account_id | `unsignedBigInteger` | nullable |
| created_by | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'status'])`
- `$table->index(['organization_id', 'maturity_date'])`

### fx_hedge_relations

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| fx_forward_id | `unsignedBigInteger` |  |
| hedge_type | `string(20)` | fair_value \| cash_flow \| net_investment |
| hedged_item_type | `string(50)` | sales_order \| purchase_order \| forecast |
| hedged_item_id | `unsignedBigInteger` | nullable |
| hedged_item_description | `string` | nullable |
| hedge_ratio | `decimal(5, 4)` | default(1.0000) — 0-1 |
| designation_date | `date` |  |
| dedesignation_date | `date` | nullable |
| status | `enum(['designated', 'dedesignated', 'expired'])` | default('designated') |
| effectiveness_notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'fx_forward_id'])`

Foreign keys:

- `$table->foreign('fx_forward_id')->references('id')->on('fx_forwards')->cascadeOnDelete()`

### fx_valuations

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| fx_forward_id | `unsignedBigInteger` |  |
| valuation_date | `date` |  |
| spot_rate | `decimal(18, 8)` |  |
| fair_value | `decimal(18, 4)` | mark-to-market |
| fair_value_change | `decimal(18, 4)` | vs previous period |
| effective_portion | `decimal(18, 4)` | default(0) — cash flow hedge |
| ineffective_portion | `decimal(18, 4)` | default(0) |
| journal_entry_id | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['fx_forward_id', 'valuation_date'])`

Foreign keys:

- `$table->foreign('fx_forward_id')->references('id')->on('fx_forwards')->cascadeOnDelete()`

### intercompany_reconciliation_matches

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| session_id | `unsignedBigInteger` |  |
| receivable_item_id | `unsignedBigInteger` | -> intercompany_reconciliation_items |
| payable_item_id | `unsignedBigInteger` | -> intercompany_reconciliation_items |
| receivable_amount | `decimal(18, 4)` |  |
| payable_amount | `decimal(18, 4)` |  |
| difference | `decimal(18, 4)` | default(0) — payable - receivable |
| currency | `string(3)` | default('SAR') |
| match_type | `enum(['auto', 'manual'])` | default('auto') |
| status | `enum(['proposed', 'confirmed', 'disputed'])` | default('proposed') |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['session_id', 'status'])`

### intercompany_reconciliation_sessions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` | initiating org |
| session_number | `string(30)` | unique — ICR-2026-0001 |
| fiscal_year | `string(4)` |  |
| period | `unsignedTinyInteger` | 1-12 |
| status | `enum(['draft', 'running', 'completed', 'closed'])` | default('draft') |
| items_count | `unsignedInteger` | default(0) |
| matched_count | `unsignedInteger` | default(0) |
| unmatched_count | `unsignedInteger` | default(0) |
| matched_amount | `decimal(18, 4)` | default(0) |
| difference_amount | `decimal(18, 4)` | default(0) |
| run_by | `unsignedBigInteger` | nullable |
| completed_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'fiscal_year', 'period'], 'ic_recon_sessions_org_fy_period_idx')`

### intercompany_reconciliation_items

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| session_id | `unsignedBigInteger` |  |
| organization_id | `unsignedBigInteger` |  |
| source_type | `string(50)` | invoice \| purchase_order \| journal_entry |
| source_id | `unsignedBigInteger` |  |
| reference_number | `string(100)` | IC key used for matching |
| amount | `decimal(18, 4)` |  |
| currency | `string(3)` | default('SAR') |
| transaction_date | `date` |  |
| counterparty_organization_id | `unsignedBigInteger` | nullable |
| counterparty_reference | `string(100)` | nullable |
| item_type | `enum(['payable', 'receivable'])` |  |
| match_status | `enum(['unmatched', 'matched', 'disputed', 'excluded'])` | default('unmatched') |
| match_id | `unsignedBigInteger` | nullable — -> intercompany_reconciliation_matches |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['session_id', 'match_status'])`
- `$table->index(['organization_id', 'reference_number'], 'icr_items_org_reference_index')`

Foreign keys:

- `$table->foreign('session_id')->references('id')->on('intercompany_reconciliation_sessions')->cascadeOnDelete()`

### material_ledger_closing_entries

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), name('mlce_org_fk') |
| material_ledger_record_id | `foreignId` | constrained('material_ledger_records'), name('mlce_mlr_fk') |
| period | `unsignedTinyInteger` |  |
| fiscal_year | `unsignedSmallInteger` |  |
| total_price_difference | `decimal(18, 4)` |  |
| revaluation_amount | `decimal(18, 4)` |  |
| actual_price_calculated | `decimal(18, 4)` |  |
| run_by | `foreignId` | nullable, constrained('users'), name('mlce_run_by_fk') |
| run_at | `dateTime` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'period', 'fiscal_year'], 'mlce_org_period_fy_idx')`

### material_ledger_documents

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), name('mld_org_fk') |
| material_ledger_record_id | `foreignId` | constrained('material_ledger_records'), name('mld_mlr_fk') |
| document_type | `enum(['goods_receipt', 'goods_issue', 'invoice', 'transfer', 'adjustment', 'closing', ])` | default('goods_receipt') |
| reference_type | `string(50)` | nullable |
| reference_id | `unsignedBigInteger` | nullable |
| quantity | `decimal(18, 4)` |  |
| standard_value | `decimal(18, 4)` |  |
| actual_value | `decimal(18, 4)` | nullable |
| price_difference | `decimal(18, 4)` | default(0) |
| posting_date | `date` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['material_ledger_record_id'], 'mld_mlr_idx')`

### material_ledger_price_differences

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained('organizations'), name('mlpd_org_fk') |
| ml_closing_entry_id | `foreignId` | constrained('material_ledger_closing_entries'), name('mlpd_ce_fk') |
| product_id | `foreignId` | constrained('products'), name('mlpd_product_fk') |
| category | `enum(['purchase_price_variance', 'exchange_rate_difference', 'invoice_difference', 'production_variance', ])` | default('purchase_price_variance') |
| amount | `decimal(18, 4)` |  |
| quantity_affected | `decimal(18, 4)` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['ml_closing_entry_id'], 'mlpd_ce_idx')`

### profitability_segment_values

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), name('psv_org_fk') |
| profitability_segment_id | `foreignId` | constrained('profitability_segments'), name('psv_seg_fk') |
| copa_dimension_id | `foreignId` | nullable, constrained('copa_dimensions'), name('psv_copa_dim_fk') |
| period | `unsignedTinyInteger` |  |
| fiscal_year | `unsignedSmallInteger` |  |
| revenue | `decimal(18, 4)` | default(0) |
| cost_of_sales | `decimal(18, 4)` | default(0) |
| gross_margin | `decimal(18, 4)` | default(0) |
| overhead_costs | `decimal(18, 4)` | default(0) |
| net_margin | `decimal(18, 4)` | default(0) |
| quantity_sold | `decimal(18, 4)` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['profitability_segment_id', 'period', 'fiscal_year'], 'psv_seg_period_fy_idx')`

### statistical_key_figure_values

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), name('skfv_org_fk') |
| statistical_key_figure_id | `foreignId` | constrained('statistical_key_figures'), name('skfv_skf_fk') |
| cost_center_id | `foreignId` | nullable, constrained('cost_centers'), name('skfv_cc_fk') |
| profit_center_id | `foreignId` | nullable, constrained('profit_centers'), name('skfv_pc_fk') |
| period | `unsignedTinyInteger` | 1-12 |
| fiscal_year | `unsignedSmallInteger` |  |
| value | `decimal(18, 4)` |  |
| posted_by | `foreignId` | nullable, constrained('users'), name('skfv_posted_by_fk') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'statistical_key_figure_id', 'cost_center_id', 'profit_center_id', 'period', 'fiscal_year'], 'skfv_unique_posting')`

### variance_analysis_items

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), name('vai_org_fk') |
| variance_analysis_run_id | `foreignId` | constrained('variance_analysis_runs'), name('vai_run_fk') |
| reference_type | `string(50)` | 'work_order', 'process_order', 'cost_center' |
| reference_id | `unsignedBigInteger` |  |
| cost_element_id | `foreignId` | nullable, constrained('cost_elements'), name('vai_ce_fk') |
| variance_category | `enum(['price_variance', 'quantity_variance', 'efficiency_variance', 'spending_variance', 'resource_usage_variance', 'remaining_input_variance', 'output_price_variance', 'mixed_price_variance', ])` |  |
| standard_cost | `decimal(18, 4)` | default(0) |
| actual_cost | `decimal(18, 4)` | default(0) |
| variance_amount | `decimal(18, 4)` | default(0) |
| variance_percent | `decimal(8, 4)` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['variance_analysis_run_id'], 'vai_run_idx')`
- `$table->index(['reference_type', 'reference_id'], 'vai_ref_idx')`

## 0020_admin.php

### platform_admin_roles

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| name | `string` |  |
| slug | `string(50)` | unique |
| description | `text` | nullable |
| permissions | `json` | List of permission slugs |
| is_system | `boolean` | default(false) — Cannot be deleted |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

### platform_admins

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| name | `string` |  |
| email | `string` | unique |
| phone | `string` | nullable |
| password | `string` |  |
| role | `string(30)` | default('admin') — super_admin, admin, support, finance, viewer |
| avatar | `string` | nullable |
| is_active | `boolean` | default(true) |
| is_2fa_enabled | `boolean` | default(false) |
| two_factor_secret | `string` | nullable |
| two_factor_recovery_codes | `json` | nullable |
| permissions | `json` | nullable — Override role-based permissions |
| last_login_at | `timestamp` | nullable |
| last_login_ip | `string` | nullable |
| remember_token | `string(100)` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['role', 'is_active'])`

### admin_ip_whitelist

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| admin_id | `foreignId` | nullable, constrained('platform_admins'), cascadeOnDelete |
| ip_address | `string(45)` |  |
| description | `string` | nullable |
| is_active | `boolean` | default(true) |
| created_by | `foreignId` | constrained('platform_admins'), cascadeOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['admin_id', 'ip_address'])`
- `$table->index(['ip_address', 'is_active'])`

### admin_notifications

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| admin_id | `foreignId` | nullable, constrained('platform_admins'), cascadeOnDelete |
| type | `string(100)` | new_organization, subscription_expiring, system_alert, support_ticket |
| title | `string` |  |
| message | `text` |  |
| severity | `string(20)` | default('info') — info, warning, error, critical |
| data | `json` | nullable |
| action_url | `string` | nullable |
| is_read | `boolean` | default(false) |
| read_at | `timestamp` | nullable |
| is_dismissed | `boolean` | default(false) |
| dismissed_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['admin_id', 'is_read'])`
- `$table->index(['type', 'created_at'])`

### feature_flags

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| name | `string` |  |
| code | `string(100)` | unique |
| description | `text` | nullable |
| is_enabled | `boolean` | default(false) |
| rollout_type | `string(30)` | default('all') — all, percentage, specific, subscription_plan |
| rollout_percentage | `unsignedTinyInteger` | nullable |
| specific_organization_ids | `json` | nullable |
| specific_subscription_plans | `json` | nullable |
| starts_at | `timestamp` | nullable |
| ends_at | `timestamp` | nullable |
| created_by | `foreignId` | nullable, constrained('platform_admins'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['is_enabled', 'rollout_type'])`

### platform_admin_sessions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| admin_id | `foreignId` | constrained('platform_admins'), cascadeOnDelete |
| session_token | `string(64)` | unique |
| ip_address | `string(45)` |  |
| user_agent | `text` |  |
| device_type | `string(30)` | nullable |
| browser | `string(50)` | nullable |
| os | `string(50)` | nullable |
| last_activity_at | `timestamp` |  |
| expires_at | `timestamp` |  |
| is_revoked | `boolean` | default(false) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['admin_id', 'is_revoked'])`
- `$table->index(['session_token'])`

### platform_permissions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| name | `string` |  |
| slug | `string(100)` | unique |
| module | `string(50)` | organizations, users, billing, support, system |
| description | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

### platform_settings

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| key | `string(100)` | unique |
| value | `text` | nullable |
| type | `string(30)` | default('string') — string, integer, boolean, json, encrypted |
| group | `string(50)` | default('general') — general, email, security, billing, integrations |
| description | `text` | nullable |
| is_public | `boolean` | default(false) — Can be fetched by frontend |
| updated_by | `foreignId` | nullable, constrained('platform_admins'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['group'])`

### system_announcements

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| admin_id | `foreignId` | nullable, constrained('platform_admins'), nullOnDelete |
| title | `string` |  |
| content | `text` |  |
| type | `string(30)` | default('info') — info, warning, maintenance, feature, critical |
| target_audience | `string(30)` | default('all') — all, organizations, admins, specific |
| target_organization_ids | `json` | nullable — For specific targeting |
| target_subscription_plans | `json` | nullable |
| is_dismissible | `boolean` | default(true) |
| show_banner | `boolean` | default(false) |
| banner_color | `string(7)` | nullable |
| action_url | `string` | nullable |
| action_text | `string` | nullable |
| starts_at | `timestamp` |  |
| ends_at | `timestamp` | nullable |
| status | `string(20)` | default('draft') — draft, scheduled, published, archived |
| published_at | `timestamp` | nullable |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['is_active', 'starts_at', 'ends_at'])`

### dim_time

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| full_date | `date` | unique |
| day_of_week | `tinyInteger` |  |
| day_name | `string(10)` |  |
| day_of_month | `tinyInteger` |  |
| week_of_year | `tinyInteger` |  |
| month_number | `tinyInteger` |  |
| month_name | `string(10)` |  |
| quarter | `tinyInteger` |  |
| year | `smallInteger` |  |
| fiscal_year | `smallInteger` |  |
| fiscal_period | `tinyInteger` |  |
| is_weekend | `boolean` | default(false) |
| is_holiday | `boolean` | default(false) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['year', 'month_number'], 'dim_time_year_month_idx')`
- `$table->index(['fiscal_year', 'fiscal_period'], 'dim_time_fiscal_idx')`

### discount_codes

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| code | `string(50)` | unique |
| name | `string` |  |
| description | `text` | nullable |
| discount_type | `string(20)` | percentage, fixed_amount |
| discount_value | `decimal(15, 2)` |  |
| max_discount_amount | `decimal(15, 2)` | nullable — Cap for percentage discounts |
| min_order_amount | `decimal(15, 2)` | nullable |
| applies_to | `string(30)` | default('all') — all, specific_plans, addons |
| applicable_plan_ids | `json` | nullable |
| max_uses | `unsignedInteger` | nullable |
| max_uses_per_org | `unsignedInteger` | default(1) |
| times_used | `unsignedInteger` | default(0) |
| starts_at | `date` |  |
| expires_at | `date` | nullable |
| is_active | `boolean` | default(true) |
| created_by | `foreignId` | constrained('platform_admins'), cascadeOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['code', 'is_active'])`

### subscription_addons

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| name | `string` |  |
| code | `string(30)` | unique |
| description | `text` | nullable |
| addon_type | `string(30)` | users, storage, api_calls, feature, module |
| price | `decimal(15, 2)` |  |
| pricing_model | `string(20)` | flat, per_unit, tiered |
| billing_cycle | `string(20)` | monthly, yearly, one_time |
| unit_quantity | `unsignedInteger` | nullable — e.g., 5 users, 10GB storage |
| unit_label | `string` | nullable — "users", "GB", etc. |
| compatible_plans | `json` | nullable — Plan IDs this addon works with |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['addon_type', 'is_active'])`

### subscription_plans

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| name | `string` |  |
| code | `string(30)` | unique |
| description | `text` | nullable |
| tier | `string(20)` | free, starter, professional, enterprise |
| billing_cycle | `string(20)` | monthly, yearly, one_time |
| base_price | `decimal(15, 2)` |  |
| currency_code | `string(3)` | default('USD') |
| max_users | `unsignedInteger` | nullable — NULL = unlimited |
| max_branches | `unsignedInteger` | nullable |
| storage_limit_mb | `unsignedBigInteger` | nullable |
| max_invoices_per_month | `unsignedInteger` | nullable |
| max_products | `unsignedInteger` | nullable |
| max_customers | `unsignedInteger` | nullable |
| max_employees | `unsignedInteger` | nullable |
| api_calls_per_month | `unsignedInteger` | nullable |
| included_modules | `json` | ['sales', 'purchase', 'inventory', 'accounting', 'hr', 'crm', 'manufacturing'] |
| features | `json` | nullable — Additional feature flags |
| trial_days | `unsignedSmallInteger` | default(0) |
| trial_requires_card | `boolean` | default(false) |
| is_public | `boolean` | default(true) — Visible on pricing page |
| is_popular | `boolean` | default(false) — Highlight as popular |
| display_order | `unsignedSmallInteger` | default(0) |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['tier', 'is_active'])`

### metered_pricing_tiers

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| plan_id | `foreignId` | constrained('subscription_plans'), cascadeOnDelete |
| metric_type | `string(50)` |  |
| from_quantity | `unsignedBigInteger` |  |
| to_quantity | `unsignedBigInteger` | nullable — NULL = unlimited |
| price_per_unit | `decimal(15, 6)` |  |
| unit_label | `string(50)` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['plan_id', 'metric_type'])`

### budget_transfers

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| transfer_number | `string` | unique — BT-2026-00001 |
| from_budget_id | `unsignedBigInteger` |  |
| from_budget_line_id | `unsignedBigInteger` |  |
| to_budget_id | `unsignedBigInteger` |  |
| to_budget_line_id | `unsignedBigInteger` |  |
| amount | `decimal(15, 2)` |  |
| reason | `string` |  |
| notes | `text` | nullable |
| status | `string` | default('draft') — draft\|submitted\|approved\|rejected\|posted |
| requested_by | `unsignedBigInteger` |  |
| approved_by | `unsignedBigInteger` | nullable |
| posted_by | `unsignedBigInteger` | nullable |
| approved_at | `timestamp` | nullable |
| posted_at | `timestamp` | nullable |
| rejection_reason | `string` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'status'])`
- `$table->index(['from_budget_line_id'])`
- `$table->index(['to_budget_line_id'])`

## 0030_core.php

### business_partners

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| bp_number | `string(30)` | unique — auto-generated BP-XXXXX |
| bp_category | `string(10)` | default('ORG') — ORG \| PERSON |
| name | `string` |  |
| name2 | `string` | nullable — legal name / trade name |
| search_term | `string(100)` | nullable |
| email | `string` | nullable |
| phone | `string` | nullable |
| mobile | `string` | nullable |
| website | `string` | nullable |
| tax_id | `string(50)` | nullable |
| vat_number | `string(50)` | nullable |
| commercial_reg | `string(50)` | nullable |
| street | `string` | nullable |
| city | `string` | nullable |
| state | `string` | nullable |
| postal_code | `string(20)` | nullable |
| country | `string(2)` | nullable — ISO alpha-2 |
| contact_id | `unsignedBigInteger` | nullable — -> contacts |
| supplier_id | `unsignedBigInteger` | nullable — -> suppliers |
| is_active | `boolean` | default(true) |
| metadata | `json` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'bp_number'])`
- `$table->index(['organization_id', 'contact_id'])`
- `$table->index(['organization_id', 'supplier_id'])`

### business_partner_roles

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| business_partner_id | `unsignedBigInteger` |  |
| role_code | `string(20)` | FLCU00 \| FLVN00 \| BUP001 \| BUP002 |
| role_name | `string` |  |
| valid_from | `date` | nullable |
| valid_to | `date` | nullable |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['business_partner_id', 'role_code'])`

Foreign keys:

- `$table->foreign('business_partner_id')->references('id')->on('business_partners')->cascadeOnDelete()`

### class_assignments

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), name('ca_org_fk') |
| classification_class_id | `foreignId` | constrained('classification_classes'), name('ca_class_fk') |
| object_type | `string(50)` |  |
| object_id | `unsignedBigInteger` |  |
| assigned_at | `dateTime` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['classification_class_id', 'object_type', 'object_id'], 'ca_class_obj_unq')`
- `$table->index(['object_type', 'object_id'], 'ca_obj_idx')`

### class_characteristic_values

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), name('ccv_org_fk') |
| class_characteristic_id | `foreignId` | constrained('class_characteristics'), name('ccv_char_fk') |
| object_type | `string(50)` |  |
| object_id | `unsignedBigInteger` |  |
| text_value | `string(500)` | nullable |
| numeric_value | `decimal(18, 4)` | nullable |
| date_value | `date` | nullable |
| boolean_value | `boolean` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['class_characteristic_id', 'object_type', 'object_id'], 'ccv_char_obj_unq')`
- `$table->index(['object_type', 'object_id'], 'ccv_obj_idx')`

### class_characteristics

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), name('cchar_org_fk') |
| classification_class_id | `foreignId` | constrained('classification_classes'), name('cchar_class_fk') |
| characteristic_code | `string(30)` |  |
| characteristic_name | `string(100)` |  |
| data_type | `enum(['text', 'numeric', 'date', 'boolean', 'list'])` | default('text') |
| unit_of_measure | `string(20)` | nullable |
| is_required | `boolean` | default(false) |
| is_searchable | `boolean` | default(true) |
| min_value | `decimal(18, 4)` | nullable |
| max_value | `decimal(18, 4)` | nullable |
| allowed_values | `json` | nullable |
| sort_order | `unsignedSmallInteger` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['classification_class_id', 'characteristic_code'], 'cchar_class_code_unq')`

### dashboard_widgets

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| code | `string` | unique — total_sales, revenue_chart, etc. |
| name | `string` |  |
| description | `text` | nullable |
| category | `string` | kpi, chart, table, list, custom |
| type | `string` | number, currency, percentage, line_chart, bar_chart, pie_chart, table, list |
| default_config | `json` | nullable |
| available_sizes | `json` | nullable — ["1x1", "2x1", "2x2", "4x2"] |
| data_source | `string` | nullable — Service method or endpoint |
| permission | `string` | nullable — Required permission |
| module | `string` | nullable — sales, inventory, accounting |
| is_premium | `boolean` | default(false) |
| is_active | `boolean` | default(true) |
| sort_order | `integer` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

### edi_message_segments

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained('organizations'), name('ems_org_fk') |
| edi_message_id | `foreignId` | constrained('edi_messages'), name('ems_message_fk') |
| segment_id | `string(10)` |  |
| segment_sequence | `unsignedSmallInteger` |  |
| segment_data | `json` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['edi_message_id'], 'ems_message_idx')`

### edi_messages

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), name('em_org_fk') |
| edi_partner_id | `foreignId` | constrained('edi_partners'), name('em_partner_fk') |
| message_type | `string(50)` |  |
| direction | `enum(['inbound', 'outbound'])` | default('inbound') |
| status | `enum(['received', 'processing', 'processed', 'failed', 'sent', 'acknowledged', ])` | default('received') |
| control_number | `string(50)` | nullable |
| functional_acknowledgment | `string(50)` | nullable |
| raw_content | `longText` | nullable |
| parsed_content | `json` | nullable |
| reference_type | `string(50)` | nullable |
| reference_id | `unsignedBigInteger` | nullable |
| error_message | `text` | nullable |
| received_at | `dateTime` | nullable |
| processed_at | `dateTime` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'direction', 'status'], 'em_org_dir_status_idx')`
- `$table->index(['edi_partner_id'], 'em_partner_idx')`
- `$table->index(['message_type', 'direction'], 'em_type_dir_idx')`

### failed_jobs_monitor

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| job_class | `string(500)` |  |
| queue | `string(255)` | default('default') |
| payload | `longText` |  |
| exception | `longText` |  |
| failed_at | `timestamp` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('failed_at', 'idx_failed_at')`

Added by later migrations:

- `0480_mysql_indexes.php`: `CREATE INDEX idx_job_class ON failed_jobs_monitor (job_class(191))`

### languages

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| code | `string(10)` | unique — en, ar, hi, ur, etc. |
| name | `string` | English, Arabic, Hindi |
| native_name | `string` | English, العربية, हिन्दी |
| direction | `string(3)` | default('ltr') — ltr, rtl |
| locale | `string` | en_US, ar_SA, hi_IN |
| flag_icon | `string` | nullable — emoji or icon code |
| is_active | `boolean` | default(true) |
| is_default | `boolean` | default(false) |
| sort_order | `integer` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

### module_definitions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| name | `string` |  |
| code | `string(50)` | unique — sales, purchase, inventory, accounting, hr, crm, manufacturing, etc. |
| group | `string(50)` | core, finance, operations, hr, marketing, advanced |
| description | `text` | nullable |
| icon | `string` | nullable |
| sub_modules | `json` | nullable — ['invoices', 'quotations', 'payments'] |
| required_modules | `json` | nullable — Dependencies: inventory requires accounting |
| min_subscription_tier | `string(20)` | default('free') — free, starter, professional, enterprise |
| is_core | `boolean` | default(false) — Core modules can't be disabled |
| is_active | `boolean` | default(true) |
| display_order | `unsignedSmallInteger` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

### module_readiness_checks

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| module | `string(50)` |  |
| check_key | `string(100)` |  |
| check_name | `string(200)` |  |
| description | `text` | nullable |
| severity | `enum(['error', 'warning', 'info'])` | default('error') |
| is_active | `boolean` | default(true) |
| order | `tinyInteger` | unsigned, default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['module', 'check_key'], 'mrc_module_check_unique')`

### organizations

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| name | `string` |  |
| legal_name | `string` | nullable |
| slug | `string` | unique |
| country_code | `string(2)` | SA, AE, QA, OM, BH, KW, IN |
| tax_scheme | `string(10)` | default('VAT') — VAT, GST, NONE |
| tax_number | `text` | nullable |
| base_currency | `string(3)` | default('SAR') |
| fiscal_year_start_month | `unsignedTinyInteger` | default(1) — 1-12 |
| fiscal_year_start_day | `unsignedTinyInteger` | default(1) — 1-31 |
| email | `string` | nullable |
| phone | `string(20)` | nullable |
| website | `string` | nullable |
| address_line_1 | `string` | nullable |
| address_line_2 | `string` | nullable |
| city | `string` | nullable |
| state | `string` | nullable |
| postal_code | `string(20)` | nullable |
| settings | `json` | nullable |
| logo_url | `string` | nullable |
| status | `string(30)` | default('active') — active, suspended, inactive |
| is_active | `boolean` | default(true) |
| activated_at | `timestamp` | nullable |
| suspended_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |
| subscription_tier | `string(20)` | default('standard') |
| subscription_expires_at | `timestamp` | nullable |

Indexes:

- `$table->index('country_code')`
- `$table->index('tax_scheme')`
- `$table->index('is_active')`

Added by later migrations:

- `0550_organization_parent.php`: `$table->foreignId('parent_organization_id')->nullable()->after('slug')->constrained('organizations')->nullOnDelete()`
- `0550_organization_parent.php`: `$table->index('parent_organization_id', 'organizations_parent_index')`

### api_call_logs

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | nullable, constrained('organizations'), nullOnDelete |
| service | `string(100)` | 'MasaarClient', 'ZatcaClient', etc. |
| method | `string(10)` | GET, POST, etc. |
| url | `string(2048)` |  |
| request_headers | `json` | nullable |
| request_body | `json` | nullable |
| response_status | `integer` | nullable |
| response_body | `json` | nullable |
| duration_ms | `integer` | nullable |
| status | `string(20)` | default('success') — success, error, timeout |
| error_message | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'service', 'created_at'])`
- `$table->index('status')`

### approval_workflows

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | nullable, unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| name | `string` |  |
| code | `string` | nullable |
| description | `text` | nullable |
| approvable_type | `string(100)` | nullable — App\Models\Sales\Invoice, etc. |
| min_amount | `decimal(15, 4)` | nullable |
| max_amount | `decimal(15, 4)` | nullable |
| conditions | `json` | nullable |
| priority | `unsignedTinyInteger` | default(0) |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'code'])`
- `$table->index(['organization_id', 'approvable_type', 'is_active'], 'approval_wf_org_approvable_active_idx')`

### approval_workflow_steps

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| approval_workflow_id | `foreignId` | constrained('approval_workflows'), cascadeOnDelete |
| name | `string` |  |
| sequence | `unsignedInteger` | default(1) |
| approver_type | `string(30)` | user, role, department_head, reporting_manager, custom |
| approver_id | `unsignedBigInteger` | nullable |
| approver_custom | `json` | nullable |
| action_type | `string(30)` | nullable |
| condition | `json` | nullable |
| conditions | `json` | nullable |
| requires_all | `boolean` | default(false) |
| min_approvers | `unsignedInteger` | default(1) |
| timeout_hours | `unsignedInteger` | nullable |
| can_skip | `boolean` | default(false) |
| can_delegate | `boolean` | default(false) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['approval_workflow_id', 'sequence'])`

### branches

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| name | `string` |  |
| code | `string(20)` | Branch code (unique within org) |
| address_line_1 | `string` | nullable |
| address_line_2 | `string` | nullable |
| city | `string` | nullable |
| state | `string` | nullable |
| postal_code | `string(20)` | nullable |
| country_code | `string(2)` | nullable |
| phone | `string(20)` | nullable |
| email | `string` | nullable |
| tax_number | `string(50)` | nullable |
| compliance_status | `string(20)` | default('pending') — pending, active, suspended |
| is_default | `boolean` | default(false) |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |
| zatca_branch_id | `string` | nullable |
| zatca_onboarding_status | `string` | nullable |
| zatca_certificate_expires_at | `dateTime` | nullable |

Indexes:

- `$table->unique(['organization_id', 'code'])`
- `$table->index('is_active')`
- `$table->index('is_default')`

### classification_classes

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations') |
| class_code | `string(30)` |  |
| class_name | `string(100)` |  |
| object_type | `string(50)` |  |
| description | `text` | nullable |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'class_code', 'object_type'], 'cc_org_code_type_unq')`
- `$table->index(['organization_id', 'object_type'], 'cc_org_type_idx')`

### custom_field_definitions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| entity_type | `string(50)` | invoice, customer, product, employee, etc. |
| field_name | `string(50)` | Internal name (snake_case) |
| field_label | `string` | Display label |
| field_type | `string(30)` | text, number, decimal, date, datetime, boolean, select, multiselect, textarea, file, url, email, phone |
| description | `text` | nullable |
| options | `json` | nullable — For select/multiselect: [{value: 'x', label: 'X'}] |
| validation | `json` | nullable — {required: true, min: 0, max: 100, pattern: '', etc.} |
| default_value | `string` | nullable |
| placeholder | `string` | nullable |
| display_order | `unsignedInteger` | default(0) |
| field_group | `string` | nullable — Group fields together in UI |
| is_required | `boolean` | default(false) |
| is_unique | `boolean` | default(false) |
| is_searchable | `boolean` | default(false) |
| is_filterable | `boolean` | default(false) |
| show_in_list | `boolean` | default(false) — Show in list/table view |
| show_in_form | `boolean` | default(true) |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'entity_type', 'field_name'], 'cf_defs_org_entity_field_unique')`
- `$table->index(['organization_id', 'entity_type', 'is_active'], 'cf_defs_org_entity_active_idx')`

### custom_field_groups

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| entity_type | `string(50)` |  |
| name | `string` |  |
| slug | `string(50)` |  |
| description | `text` | nullable |
| display_order | `unsignedInteger` | default(0) |
| is_collapsible | `boolean` | default(true) |
| is_collapsed_default | `boolean` | default(false) |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'entity_type', 'slug'])`

### custom_field_values

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| field_definition_id | `foreignId` | constrained('custom_field_definitions'), cascadeOnDelete |
| entity_type | `string` |  |
| entity_id | `unsignedBigInteger` |  |
| value_text | `text` | nullable |
| value_number | `decimal(20, 6)` | nullable |
| value_date | `date` | nullable |
| value_datetime | `datetime` | nullable |
| value_boolean | `boolean` | nullable |
| value_json | `json` | nullable — For multiselect, file references, etc. |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `morphs('entity')`
- `$table->unique(['field_definition_id', 'entity_type', 'entity_id'], 'custom_field_value_unique')`

### edi_partners

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations') |
| partner_code | `string(50)` |  |
| partner_name | `string(100)` |  |
| partner_type | `enum(['vendor', 'customer', 'bank', 'carrier', 'other'])` | default('vendor') |
| edi_standard | `enum(['edifact', 'x12', 'ubl', 'idoc', 'custom'])` | default('edifact') |
| is_active | `boolean` | default(true) |
| contact_id | `foreignId` | nullable, constrained('contacts'), name('ep_contact_fk') |
| interchange_id | `string(50)` | nullable |
| interchange_qualifier | `string(10)` | nullable |
| test_mode | `boolean` | default(false) |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'partner_code'], 'ep_org_code_unq')`

### email_templates

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | nullable, constrained, cascadeOnDelete |
| code | `string(50)` | unique — invoice_created, payment_reminder, etc. |
| name | `string(100)` |  |
| subject | `string(255)` |  |
| body_html | `text` |  |
| body_text | `text` | nullable |
| from_name | `string(100)` | nullable |
| reply_to | `string(100)` | nullable |
| cc | `string(255)` | nullable |
| bcc | `string(255)` | nullable |
| variables | `json` | nullable — Available placeholder variables |
| language | `string(5)` | default('en') |
| is_active | `boolean` | default(true) |
| is_system | `boolean` | default(false) — System templates cannot be deleted |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'code', 'language'])`

### gdpr_processing_activities

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| activity_name | `string` |  |
| purpose | `text` |  |
| legal_basis | `enum(['consent', 'contract', 'legal_obligation', 'vital_interests', 'public_task', 'legitimate_interests'])` |  |
| data_categories | `json` |  |
| recipient_categories | `json` | nullable |
| retention_period_days | `unsignedInteger` | nullable |
| third_country_transfers | `boolean` | default(false) |
| dpia_required | `boolean` | default(false) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete()`

### import_templates

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| name | `string` |  |
| entity_type | `string(50)` |  |
| column_mapping | `json` |  |
| options | `json` | nullable |
| is_default | `boolean` | default(false) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'name', 'entity_type'])`

### number_sequences

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | nullable, constrained, cascadeOnDelete |
| sequence_key | `string(100)` | nullable, unique |
| current_value | `unsignedBigInteger` | default(0) |
| branch_id | `foreignId` | nullable, constrained, nullOnDelete |
| type | `string(50)` | nullable |
| prefix | `string(20)` | nullable |
| suffix | `string(20)` | nullable |
| current_number | `unsignedBigInteger` | default(0) |
| padding | `unsignedTinyInteger` | default(5) |
| include_year | `boolean` | default(true) |
| include_month | `boolean` | default(false) |
| reset_yearly | `boolean` | default(true) |
| reset_monthly | `boolean` | default(false) |
| last_reset_year | `unsignedSmallInteger` | nullable |
| last_reset_month | `unsignedTinyInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('organization_id')`
- `$table->unique(['organization_id', 'branch_id', 'type'], 'number_sequences_org_branch_type')`

### onboarding_templates

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `char(36)` | unique |
| organization_id | `foreignId` | nullable, constrained, cascadeOnDelete |
| name | `string(100)` |  |
| module | `string(50)` | nullable |
| description | `text` | nullable |
| is_active | `boolean` | default(true) |
| order | `tinyInteger` | unsigned, default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'module', 'is_active'], 'ont_org_module_active_idx')`

### onboarding_steps

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| template_id | `foreignId` | constrained('onboarding_templates'), cascadeOnDelete |
| title | `string(200)` |  |
| description | `text` | nullable |
| step_type | `enum(['action', 'info', 'video', 'link'])` | default('action') |
| action_key | `string(100)` | nullable |
| help_url | `string(500)` | nullable |
| is_required | `boolean` | default(true) |
| order | `tinyInteger` | unsigned, default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['template_id', 'order'], 'ons_template_order_idx')`

### organization_branding

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| logo_url | `string` | nullable |
| logo_dark_url | `string` | nullable — For dark mode |
| favicon_url | `string` | nullable |
| login_background_url | `string` | nullable |
| primary_color | `string(20)` | default('#3498db') |
| secondary_color | `string(20)` | default('#2ecc71') |
| accent_color | `string(20)` | default('#9b59b6') |
| danger_color | `string(20)` | default('#e74c3c') |
| warning_color | `string(20)` | default('#f39c12') |
| success_color | `string(20)` | default('#27ae60') |
| info_color | `string(20)` | default('#3498db') |
| text_color | `string(20)` | default('#333333') |
| background_color | `string(20)` | default('#f8f9fa') |
| sidebar_color | `string(20)` | default('#2c3e50') |
| header_color | `string(20)` | default('#ffffff') |
| font_family | `string` | default('Inter') |
| font_family_arabic | `string` | default('Cairo') — For RTL |
| base_font_size | `integer` | default(14) |
| theme | `string` | default('light') — light, dark, auto |
| enable_dark_mode | `boolean` | default(true) |
| custom_css | `text` | nullable |
| email_header_color | `string(20)` | nullable |
| email_footer_text | `string` | nullable |
| document_watermark | `string` | nullable |
| document_footer_text | `string` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique('organization_id')`

### permissions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| name | `string` | Human-readable name |
| slug | `string` | unique — e.g., 'sales.invoices.create' |
| module | `string(50)` | e.g., 'sales', 'accounting', 'inventory' |
| description | `string` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('module')`

### print_configurations

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| branch_id | `foreignId` | nullable, constrained, nullOnDelete |
| printer_type | `string` | laser, inkjet, thermal_80, thermal_58, sunmi_v2, sunmi_v2_pro |
| default_paper_size | `string` | default('a4') |
| paper_sizes | `json` | nullable — Available paper sizes for this config |
| thermal_settings | `json` | nullable — Width, DPI, cut mode |
| margin_settings | `json` | nullable — top, right, bottom, left |
| font_settings | `json` | nullable — family, size, line_height |
| auto_cut | `boolean` | default(true) |
| open_drawer | `boolean` | default(false) |
| copies | `integer` | default(1) |
| is_default | `boolean` | default(false) |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'branch_id'])`

### print_templates

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| name | `string` |  |
| code | `string` | index — invoice_a4, invoice_thermal_80, quote_a5, etc. |
| document_type | `string` | invoice, quotation, purchase_order, credit_note, delivery_note, payment_receipt |
| paper_size | `string` | a3, a4, a5, thermal_80, thermal_58, letter, legal |
| orientation | `string` | default('portrait') — portrait, landscape |
| template_content | `text` | nullable — Custom HTML/Blade content |
| template_file | `string` | nullable — Reference to blade file |
| settings | `json` | nullable — Font size, margins, colors, etc. |
| sections | `json` | nullable — Which sections to show/hide |
| show_logo | `boolean` | default(true) |
| show_qr_code | `boolean` | default(true) |
| show_signature | `boolean` | default(false) |
| show_watermark | `boolean` | default(false) |
| watermark_text | `string` | nullable |
| primary_color | `string` | default('#2c3e50') |
| secondary_color | `string` | default('#3498db') |
| is_default | `boolean` | default(false) |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'code'])`
- `$table->index(['organization_id', 'document_type', 'paper_size'])`

### retention_policies

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| document_type | `string(50)` |  |
| policy_name | `string(100)` |  |
| retention_years | `integer` |  |
| jurisdiction | `enum(['saudi_arabia', 'uae', 'india', 'global'])` | default('global') |
| action_on_expiry | `enum(['archive', 'delete', 'notify_only'])` | default('notify_only') |
| legal_hold_override | `boolean` | default(true) |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'document_type', 'jurisdiction'], 'rp_org_type_jurisdiction_unique')`

### retention_schedule_runs

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| run_at | `timestamp` |  |
| documents_evaluated | `integer` | default(0) |
| documents_archived | `integer` | default(0) |
| documents_deleted | `integer` | default(0) |
| documents_skipped_legal_hold | `integer` | default(0) |
| run_log | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

### roles

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | nullable, constrained, cascadeOnDelete |
| name | `string` |  |
| slug | `string` |  |
| description | `string` | nullable |
| is_system | `boolean` | default(false) — System roles can't be deleted |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'slug'])`
- `$table->index('is_system')`

### role_menu_items

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| role_id | `foreignId` | constrained('roles'), cascadeOnDelete |
| module_id | `foreignId` | constrained('module_definitions'), cascadeOnDelete |
| menu_label | `string` |  |
| menu_icon | `string` | nullable |
| route_name | `string` | nullable |
| parent_menu | `string` | nullable — For nested menus |
| position | `unsignedSmallInteger` | default(0) |
| is_visible | `boolean` | default(true) |
| is_pinned | `boolean` | default(false) — Pinned to sidebar/favorites |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['role_id', 'position'])`

### role_module_permissions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| role_id | `foreignId` | constrained('roles'), cascadeOnDelete |
| module_id | `foreignId` | constrained('module_definitions'), cascadeOnDelete |
| can_view | `boolean` | default(false) |
| can_create | `boolean` | default(false) |
| can_edit | `boolean` | default(false) |
| can_delete | `boolean` | default(false) |
| can_export | `boolean` | default(false) |
| can_import | `boolean` | default(false) |
| can_approve | `boolean` | default(false) |
| can_print | `boolean` | default(false) |
| data_scope | `string(30)` | default('own') — own, branch, department, organization, all |
| max_amount_limit | `decimal(15, 2)` | nullable — Max transaction amount |
| max_discount_percent | `decimal(5, 2)` | nullable — Max discount they can give |
| custom_permissions | `json` | nullable — Module-specific extras |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['role_id', 'module_id'])`
- `$table->index(['role_id'])`
- `$table->index(['module_id'])`

### sso_providers

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| provider_name | `string` |  |
| protocol | `enum(['oauth2', 'saml2', 'oidc'])` |  |
| client_id | `string` | nullable |
| client_secret_encrypted | `text` | nullable |
| authorization_endpoint | `string` | nullable |
| token_endpoint | `string` | nullable |
| userinfo_endpoint | `string` | nullable |
| saml_entity_id | `string` | nullable |
| saml_sso_url | `string` | nullable |
| saml_certificate | `text` | nullable |
| attribute_mapping | `json` | nullable |
| active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete()`

### tenant_rate_limit_configs

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` | unique |
| requests_per_minute | `unsignedSmallInteger` | default(60) |
| requests_per_hour | `unsignedSmallInteger` | default(1000) |
| requests_per_day | `unsignedInteger` | default(10000) |
| burst_limit | `unsignedSmallInteger` | default(100) |
| api_key_limit | `unsignedSmallInteger` | nullable |
| is_unlimited | `boolean` | default(false) |
| custom_limits | `json` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete()`

### tracked_jobs

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| job_class | `string(255)` |  |
| job_key | `string(100)` | nullable, comment('Business key for idempotency, e.g. payroll_period_id') |
| organization_id | `unsignedBigInteger` | nullable, index |
| triggered_by_user_id | `unsignedBigInteger` | nullable |
| payload | `json` |  |
| status | `string(20)` | default('pending') — pending\|running\|succeeded\|failed\|replayed |
| attempts | `unsignedSmallInteger` | default(0) |
| last_error | `text` | nullable |
| started_at | `timestamp` | nullable |
| completed_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['job_class', 'status'])`
- `$table->index(['organization_id', 'status', 'created_at'])`
- `$table->index('job_key')`

## 0040_core_2.php

### translations

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | nullable, constrained, cascadeOnDelete |
| language_code | `string(10)` |  |
| group | `string` | validation, messages, labels, invoice, etc. |
| key | `string` | invoice.title, button.save, etc. |
| value | `text` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'language_code', 'group', 'key'], 'trans_unique')`
- `$table->index(['language_code', 'group'])`

### users

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| name | `string` |  |
| email | `string` | unique |
| email_verified_at | `timestamp` | nullable |
| password | `string` |  |
| remember_token | `string(100)` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | nullable, constrained, nullOnDelete |
| employee_id | `foreignId` | nullable |
| phone | `string(20)` | nullable |
| preferred_language | `string(5)` | default('en') |
| timezone | `string(50)` | default('Asia/Riyadh') |
| two_factor_enabled | `boolean` | default(false) |
| two_factor_secret | `text` | nullable |
| is_active | `boolean` | default(true) |
| is_super_admin | `boolean` | default(false) |
| last_login_at | `timestamp` | nullable |
| last_login_ip | `string(45)` | nullable |
| deleted_at | `timestamp` | nullable |
| onboarding_completed_at | `timestamp` | nullable |
| deactivated_at | `timestamp` | nullable |
| deactivated_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| deactivation_reason | `string(255)` | nullable |
| roles_updated_at | `timestamp` | nullable |
| module_access | `json` | nullable |
| email_verification_code | `string(255)` | nullable |
| email_verification_code_sent_at | `timestamp` | nullable |
| two_factor_recovery_codes | `json` | nullable |
| two_factor_confirmed_at | `timestamp` | nullable |
| registration_source | `string(30)` | nullable |
| utm_source | `string(100)` | nullable |
| utm_medium | `string(100)` | nullable |
| utm_campaign | `string(150)` | nullable |
| utm_term | `string(150)` | nullable |
| utm_content | `string(150)` | nullable |
| referral_code | `string(50)` | nullable |
| registration_device_type | `string(20)` | nullable |
| registration_ip | `string(45)` | nullable |
| invited_by_user_id | `unsignedBigInteger` | nullable |

Indexes:

- `$table->index('organization_id')`
- `$table->index('is_active')`
- `$table->index('is_super_admin')`

Foreign keys:

- `$table->foreign('invited_by_user_id')->references('id')->on('users')->nullOnDelete()`

### activities

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| user_id | `foreignId` | nullable, constrained, nullOnDelete |
| branch_id | `foreignId` | nullable, constrained, nullOnDelete |
| subject_type | `string(100)` |  |
| subject_id | `unsignedBigInteger` |  |
| causer_type | `string(100)` | nullable |
| causer_id | `unsignedBigInteger` | nullable |
| event | `string(50)` | created, updated, deleted, sent, approved, etc. |
| description | `string(500)` | nullable |
| properties | `json` | nullable — Additional properties |
| old_values | `json` | nullable — Values before change |
| new_values | `json` | nullable — Values after change |
| ip_address | `string(45)` | nullable |
| user_agent | `string(500)` | nullable |
| source | `string(30)` | default('web') — web, api, system, import |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['subject_type', 'subject_id'])`
- `$table->index(['organization_id', 'created_at'])`
- `$table->index(['user_id', 'created_at'])`
- `$table->index('event')`

### activity_logs

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| user_id | `foreignId` | nullable, constrained, nullOnDelete |
| branch_id | `foreignId` | nullable, constrained, nullOnDelete |
| action | `string(50)` | created, updated, deleted, viewed, exported, imported, approved, rejected, etc. |
| entity_type | `string(100)` | Invoice, Customer, Product, etc. |
| entity_id | `string(100)` | nullable |
| entity_name | `string` | nullable — Human-readable identifier |
| description | `string` | Human-readable description |
| old_values | `json` | nullable — Previous state |
| new_values | `json` | nullable — New state |
| changed_fields | `json` | nullable — List of changed field names |
| metadata | `json` | nullable — Additional context |
| ip_address | `string(45)` | nullable |
| user_agent | `string` | nullable |
| request_method | `string(10)` | nullable |
| request_url | `string` | nullable |
| session_id | `string(100)` | nullable |
| module | `string(50)` | nullable — sales, inventory, hr, etc. |
| severity | `string(20)` | default('info') — info, warning, error, critical |
| is_system | `boolean` | default(false) — System-generated vs user action |
| created_at | `timestamp` |  |
| impersonated_by_id | `unsignedBigInteger` | nullable |
| impersonation_session_id | `char(36)` | nullable |

Indexes:

- `$table->index(['organization_id', 'created_at'])`
- `$table->index(['organization_id', 'user_id', 'created_at'])`
- `$table->index(['organization_id', 'entity_type', 'entity_id'])`
- `$table->index(['organization_id', 'action'])`
- `$table->index(['organization_id', 'module'])`
- `$table->index('impersonation_session_id')`
- `$table->index('impersonated_by_id')`

Foreign keys:

- `$table->foreign('impersonated_by_id')->references('id')->on('users')->nullOnDelete()`

### approval_requests

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| approval_workflow_id | `foreignId` | constrained('approval_workflows'), cascadeOnDelete |
| approvable_type | `string` | nullable |
| approvable_id | `unsignedBigInteger` | nullable |
| current_step_id | `foreignId` | nullable, constrained('approval_workflow_steps'), nullOnDelete |
| submitted_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| status | `string(20)` | default('pending') |
| amount | `decimal(15, 4)` | nullable |
| notes | `text` | nullable |
| submitted_at | `timestamp` | nullable |
| completed_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `nullableMorphs('approvable')`
- `$table->index(['organization_id', 'status'])`

### approval_actions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| approval_request_id | `foreignId` | constrained('approval_requests'), cascadeOnDelete |
| workflow_step_id | `foreignId` | constrained('approval_workflow_steps'), cascadeOnDelete |
| assigned_to | `foreignId` | nullable, constrained('users'), nullOnDelete |
| status | `string(20)` | default('pending') |
| delegated_to | `foreignId` | nullable, constrained('users'), nullOnDelete |
| delegated_at | `timestamp` | nullable |
| comments | `text` | nullable |
| action_at | `timestamp` | nullable |
| action_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| expires_at | `timestamp` | nullable |
| reminder_sent | `boolean` | default(false) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['approval_request_id', 'workflow_step_id'])`
- `$table->index(['assigned_to', 'status'])`

### attachments

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| attachable_type | `string(100)` |  |
| attachable_id | `unsignedBigInteger` |  |
| file_name | `string(255)` |  |
| original_name | `string(255)` |  |
| mime_type | `string(100)` |  |
| file_size | `unsignedBigInteger` | bytes |
| disk | `string(50)` | default('local') — local, s3, etc. |
| path | `string(500)` |  |
| category | `string(50)` | nullable — receipt, contract, image, etc. |
| description | `string(500)` | nullable |
| metadata | `json` | nullable — Image dimensions, PDF pages, etc. |
| is_public | `boolean` | default(false) |
| visibility | `string(20)` | default('private') — private, organization, public |
| expires_at | `timestamp` | nullable |
| uploaded_by | `foreignId` | constrained('users'), cascadeOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['attachable_type', 'attachable_id'])`
- `$table->index(['organization_id', 'category'])`

### attachment_access_logs

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| attachment_id | `foreignId` | constrained, cascadeOnDelete |
| user_id | `foreignId` | nullable, constrained, nullOnDelete |
| action | `string(20)` | view, download, share |
| ip_address | `string(45)` | nullable |
| user_agent | `string(500)` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['attachment_id', 'action'])`

### bulk_operation_jobs

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| operation_type | `string` |  |
| total_records | `unsignedInteger` | default(0) |
| processed_records | `unsignedInteger` | default(0) |
| success_count | `unsignedInteger` | default(0) |
| failure_count | `unsignedInteger` | default(0) |
| status | `enum(['queued', 'processing', 'completed', 'failed', 'cancelled'])` | default('queued') |
| payload | `json` |  |
| result_summary | `json` | nullable |
| error_log | `json` | nullable |
| initiated_by | `unsignedBigInteger` |  |
| started_at | `timestamp` | nullable |
| completed_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'status'])`

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete()`
- `$table->foreign('initiated_by', 'bulk_op_usr_fk')->references('id')->on('users')->cascadeOnDelete()`

### change_freeze_periods

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `char(36)` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| name | `string(100)` |  |
| reason | `text` | nullable |
| starts_at | `timestamp` |  |
| ends_at | `timestamp` | nullable |
| scope | `enum(['all', 'module'])` | default('all') |
| affected_modules | `json` | nullable |
| bypass_roles | `json` | nullable |
| bypass_permission | `string(100)` | nullable |
| is_active | `boolean` | default(true) |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'is_active'])`
- `$table->index(['organization_id', 'starts_at', 'ends_at'], 'change_freeze_org_time_idx')`

### change_transport_requests

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| request_number | `string(20)` |  |
| description | `string(200)` |  |
| request_type | `string(20)` | comment('workbench/customizing/transport_of_copies') |
| category | `string(30)` | comment('feature/bugfix/configuration/data_migration') |
| target_environment | `string(20)` | comment('quality/production/staging') |
| status | `string(20)` | default('open'), comment('open/released/imported/failed') |
| created_by | `unsignedBigInteger` |  |
| released_by | `unsignedBigInteger` | nullable |
| released_at | `dateTime` | nullable |
| imported_at | `dateTime` | nullable |
| import_log | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index('request_number', 'ctr_request_number_idx')`
- `$table->index(['status', 'target_environment'], 'ctr_status_env_idx')`
- `$table->index(['created_by', 'status'], 'ctr_created_status_idx')`

Foreign keys:

- `$table->foreign('organization_id', 'ctr_org_id_fk')->references('id')->on('organizations')->onDelete('cascade')`
- `$table->foreign('created_by', 'ctr_created_by_fk')->references('id')->on('users')->onDelete('restrict')`
- `$table->foreign('released_by', 'ctr_released_by_fk')->references('id')->on('users')->onDelete('set null')`

### change_transport_logs

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| change_transport_request_id | `unsignedBigInteger` |  |
| action | `string(50)` | comment('created/object_added/released/import_started/imported/failed/rollback') |
| performed_by | `unsignedBigInteger` | nullable |
| environment | `string(20)` | nullable |
| message | `text` | nullable |
| created_at | `dateTime` |  |

Indexes:

- `$table->index('change_transport_request_id', 'ctl_req_id_idx')`

Foreign keys:

- `$table->foreign('change_transport_request_id', 'ctl_request_id_fk')->references('id')->on('change_transport_requests')->onDelete('cascade')`
- `$table->foreign('performed_by', 'ctl_performed_by_fk')->references('id')->on('users')->onDelete('set null')`

### change_transport_object_assignments

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| change_transport_request_id | `unsignedBigInteger` |  |
| user_id | `unsignedBigInteger` |  |
| assigned_at | `dateTime` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('change_transport_request_id', 'ctoa_request_id_fk')->references('id')->on('change_transport_requests')->onDelete('cascade')`
- `$table->foreign('user_id', 'ctoa_user_id_fk')->references('id')->on('users')->onDelete('cascade')`

### change_transport_objects

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| change_transport_request_id | `unsignedBigInteger` |  |
| object_type | `string(50)` | comment('migration/config/route/permission/setting') |
| object_name | `string(200)` |  |
| object_key | `string(200)` | nullable |
| change_type | `string(20)` | comment('create/modify/delete') |
| payload | `json` | nullable |
| checksums | `string(64)` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('change_transport_request_id', 'cto_req_id_idx')`

Foreign keys:

- `$table->foreign('change_transport_request_id', 'cto_request_id_fk')->references('id')->on('change_transport_requests')->onDelete('cascade')`

### comments

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| user_id | `foreignId` | constrained, cascadeOnDelete |
| commentable_type | `string(100)` |  |
| commentable_id | `unsignedBigInteger` |  |
| parent_id | `foreignId` | nullable, constrained('comments'), cascadeOnDelete |
| content | `text` |  |
| is_internal | `boolean` | default(false) — Internal note vs customer-visible |
| is_pinned | `boolean` | default(false) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['commentable_type', 'commentable_id'])`

### dashboard_layouts

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| user_id | `foreignId` | nullable, constrained, cascadeOnDelete |
| name | `string` | default('Default') |
| type | `string` | default('main') — main, sales, inventory, finance |
| widgets | `json` | Widget configuration |
| layout | `json` | Grid layout positions |
| is_default | `boolean` | default(false) |
| is_shared | `boolean` | default(false) — Shared with org |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'user_id', 'type'])`

### document_download_tokens

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| token | `string(64)` | unique |
| document_type | `string(20)` | 'invoice', 'bill', 'receipt', 'payslip' |
| document_id | `unsignedBigInteger` |  |
| generated_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| expires_at | `dateTime` |  |
| first_accessed_at | `dateTime` | nullable |
| access_expires_at | `dateTime` | nullable |
| access_count | `smallInteger` | default(0) |
| is_revoked | `boolean` | default(false) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('token')`
- `$table->index(['document_type', 'document_id'])`
- `$table->index(['organization_id', 'expires_at'])`

### document_legal_holds

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| document_type | `string(50)` |  |
| document_id | `unsignedBigInteger` |  |
| hold_reason | `string(500)` |  |
| held_by | `foreignId` | constrained('users') |
| hold_until | `date` | nullable |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['document_type', 'document_id'], 'dlh_doc_idx')`
- `$table->index(['organization_id', 'is_active'], 'dlh_org_active_idx')`

### email_logs

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| user_id | `foreignId` | nullable, constrained, nullOnDelete |
| template_code | `string(50)` | nullable |
| emailable_type | `string(100)` | nullable — Invoice, Quotation, etc. |
| emailable_id | `unsignedBigInteger` | nullable |
| to_email | `string(255)` |  |
| to_name | `string(255)` | nullable |
| subject | `string(255)` |  |
| body_preview | `text` | nullable — First 500 chars |
| attachments | `json` | nullable |
| status | `string(20)` | default('pending') — pending, sent, failed, bounced |
| error_message | `text` | nullable |
| sent_at | `timestamp` | nullable |
| opened_at | `timestamp` | nullable |
| clicked_at | `timestamp` | nullable |
| bounced_at | `timestamp` | nullable |
| message_id | `string(255)` | nullable — External email provider message ID |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'status'])`
- `$table->index(['emailable_type', 'emailable_id'])`

### entity_views

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| user_id | `foreignId` | constrained, cascadeOnDelete |
| entity_type | `string(100)` |  |
| entity_id | `string(100)` |  |
| entity_name | `string` | nullable |
| viewed_at | `timestamp` |  |

Indexes:

- `$table->unique(['user_id', 'entity_type', 'entity_id'])`
- `$table->index(['user_id', 'viewed_at'])`

### export_jobs

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| user_id | `foreignId` | constrained, cascadeOnDelete |
| entity_type | `string(50)` | customers, invoices, products, etc. |
| format | `string(10)` | default('xlsx') — xlsx, csv, pdf |
| status | `string(20)` | default('pending') — pending, processing, completed, failed |
| filters | `json` | nullable — Applied filters for export |
| columns | `json` | nullable — Selected columns to export |
| options | `json` | nullable — Export options |
| total_records | `unsignedInteger` | default(0) |
| file_name | `string` | nullable |
| file_path | `string` | nullable |
| file_size | `unsignedBigInteger` | default(0) |
| started_at | `timestamp` | nullable |
| completed_at | `timestamp` | nullable |
| expires_at | `timestamp` | nullable — When the file will be deleted |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'status'])`
- `$table->index(['organization_id', 'entity_type'])`
- `$table->index('expires_at')`

### feature_adoption_events

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| user_id | `foreignId` | constrained, cascadeOnDelete |
| feature_key | `string(100)` |  |
| first_used_at | `timestamp` |  |
| last_used_at | `timestamp` |  |
| usage_count | `unsignedInteger` | default(1) |

Indexes:

- `$table->unique(['organization_id', 'user_id', 'feature_key'], 'fae_org_user_feature_unique')`
- `$table->index(['organization_id', 'feature_key'], 'fae_org_feature_idx')`

### organization_feature_flags

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| flag_key | `string(100)` |  |
| is_enabled | `boolean` | default(false) |
| config | `json` | nullable |
| enabled_at | `timestamp` | nullable |
| disabled_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'flag_key'], 'off_org_flag_unique')`

### feature_flag_rollout_logs

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| flag_key | `string(100)` |  |
| action | `enum(['enabled', 'disabled', 'target_added', 'target_removed', 'rollout_percentage_set', ])` |  |
| actor_id | `foreignId` | nullable, constrained('users'), nullOnDelete |
| detail | `json` | nullable |
| created_at | `timestamp` | useCurrent |

Indexes:

- `$table->index(['organization_id', 'flag_key'], 'ffrl_org_flag_idx')`

### feature_flag_targets

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| flag_key | `string(100)` |  |
| target_type | `enum(['user', 'branch', 'role', 'percentage'])` |  |
| target_id | `unsignedBigInteger` | nullable |
| percentage | `unsignedTinyInteger` | nullable |
| enabled | `boolean` | default(true) |
| notes | `text` | nullable |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'flag_key', 'target_type', 'target_id'], 'fft_org_flag_type_target_unique')`
- `$table->index(['organization_id', 'flag_key'], 'fft_org_flag_idx')`

### gdpr_data_subject_requests

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| request_type | `enum(['access', 'erasure', 'portability', 'rectification', 'restriction', 'objection'])` |  |
| requester_name | `string` |  |
| requester_email | `string` |  |
| requester_id | `unsignedBigInteger` | nullable |
| status | `enum(['received', 'verifying', 'processing', 'completed', 'rejected'])` | default('received') |
| received_at | `timestamp` |  |
| deadline_at | `timestamp` |  |
| completed_at | `timestamp` | nullable |
| rejection_reason | `text` | nullable |
| data_exported_path | `string` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete()`
- `$table->foreign('requester_id', 'gdpr_req_usr_fk')->references('id')->on('users')->nullOnDelete()`

### import_jobs

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| user_id | `foreignId` | constrained, cascadeOnDelete |
| entity_type | `string(50)` | customers, suppliers, products, employees, etc. |
| file_name | `string` |  |
| file_path | `string` |  |
| original_name | `string` |  |
| file_size | `unsignedBigInteger` | default(0) |
| status | `string(20)` | default('pending') — pending, processing, completed, failed, cancelled |
| total_rows | `unsignedInteger` | default(0) |
| processed_rows | `unsignedInteger` | default(0) |
| success_rows | `unsignedInteger` | default(0) |
| failed_rows | `unsignedInteger` | default(0) |
| skipped_rows | `unsignedInteger` | default(0) |
| column_mapping | `json` | nullable — Maps file columns to entity fields |
| options | `json` | nullable — Import options (update_existing, skip_errors, etc.) |
| errors | `json` | nullable — Array of row-level errors |
| summary | `json` | nullable — Import summary stats |
| started_at | `timestamp` | nullable |
| completed_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'status'])`
- `$table->index(['organization_id', 'entity_type'])`
- `$table->index('created_at')`

### ip_allowlist_rules

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| rule_name | `string` |  |
| ip_address | `string` | nullable |
| ip_range_start | `string` | nullable |
| ip_range_end | `string` | nullable |
| cidr_notation | `string` | nullable |
| rule_type | `enum(['allow', 'deny'])` | default('allow') |
| applies_to | `enum(['all', 'api', 'admin', 'specific_role'])` | default('all') |
| role_id | `unsignedBigInteger` | nullable |
| active | `boolean` | default(true) |
| created_by | `unsignedBigInteger` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete()`
- `$table->foreign('role_id', 'ip_allow_role_fk')->references('id')->on('roles')->nullOnDelete()`
- `$table->foreign('created_by', 'ip_allow_usr_fk')->references('id')->on('users')->cascadeOnDelete()`

### job_monitors

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` | nullable |
| job_class | `string(200)` |  |
| job_name | `string(200)` |  |
| queue_name | `string(50)` | default('default') |
| status | `string(20)` | default('queued'), comment('queued/running/completed/failed/retrying') |
| payload | `json` | nullable |
| output | `text` | nullable |
| error_message | `text` | nullable |
| attempts | `tinyInteger` | default(0) |
| max_attempts | `tinyInteger` | default(3) |
| progress_percentage | `tinyInteger` | default(0) |
| progress_message | `string(200)` | nullable |
| queued_at | `dateTime` |  |
| started_at | `dateTime` | nullable |
| completed_at | `dateTime` | nullable |
| failed_at | `dateTime` | nullable |
| next_retry_at | `dateTime` | nullable |
| run_duration_seconds | `integer` | nullable |
| triggered_by | `string(50)` | default('manual'), comment('manual/scheduled/event/system') |
| triggered_by_user_id | `unsignedBigInteger` | nullable |
| tags | `json` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['status', 'queued_at'], 'jm_status_queued_idx')`
- `$table->index(['job_class', 'status'], 'jm_class_status_idx')`
- `$table->index(['organization_id', 'status'], 'jm_org_status_idx')`
- `$table->index(['queue_name', 'status'], 'jm_queue_status_idx')`

Foreign keys:

- `$table->foreign('organization_id', 'jm_org_id_fk')->references('id')->on('organizations')->onDelete('set null')`
- `$table->foreign('triggered_by_user_id', 'jm_triggered_by_user_fk')->references('id')->on('users')->onDelete('set null')`

### job_monitor_logs

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| job_monitor_id | `unsignedBigInteger` |  |
| level | `string(10)` | comment('info/warning/error/debug') |
| message | `text` |  |
| context | `json` | nullable |
| created_at | `dateTime` |  |

Indexes:

- `$table->index(['job_monitor_id', 'level'], 'jml_monitor_level_idx')`

Foreign keys:

- `$table->foreign('job_monitor_id', 'jml_monitor_id_fk')->references('id')->on('job_monitors')->onDelete('cascade')`

### login_history

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| user_id | `foreignId` | nullable, constrained, nullOnDelete |
| email | `string` | nullable |
| ip_address | `string(45)` |  |
| user_agent | `string` | nullable |
| status | `string(20)` | success, failed, blocked, 2fa_required |
| failure_reason | `string` | nullable |
| attempted_at | `timestamp` |  |

Indexes:

- `$table->index(['user_id', 'attempted_at'])`
- `$table->index(['ip_address', 'attempted_at'])`
- `$table->index(['email', 'attempted_at'])`

### mentions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| comment_id | `foreignId` | constrained, cascadeOnDelete |
| user_id | `foreignId` | constrained, cascadeOnDelete |
| is_read | `boolean` | default(false) |
| read_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['comment_id', 'user_id'])`

### module_access_logs

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| user_id | `foreignId` | constrained, cascadeOnDelete |
| module_id | `foreignId` | constrained('module_definitions'), cascadeOnDelete |
| action | `string(50)` | view, create, edit, delete, approve, export, import |
| entity_type | `string(100)` | nullable |
| entity_id | `unsignedBigInteger` | nullable |
| was_allowed | `boolean` | default(true) |
| denial_reason | `string` | nullable — Why access was denied |
| ip_address | `string(45)` | nullable |
| accessed_at | `timestamp` |  |

Indexes:

- `$table->index(['organization_id', 'accessed_at'])`
- `$table->index(['user_id', 'accessed_at'])`
- `$table->index(['module_id', 'was_allowed'])`

### module_readiness_results

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `char(36)` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| module | `string(50)` |  |
| run_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| run_at | `timestamp` |  |
| overall_status | `enum(['pass', 'fail', 'warning'])` | default('pass') |
| results | `json` |  |
| created_at | `timestamp` | useCurrent |

Indexes:

- `$table->index(['organization_id', 'module'], 'mrr_org_module_idx')`

### notification_preferences

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| user_id | `foreignId` | constrained, onDelete('cascade') |
| notification_type | `string(100)` |  |
| email_enabled | `boolean` | default(true) |
| database_enabled | `boolean` | default(true) |
| push_enabled | `boolean` | default(true) |
| sms_enabled | `boolean` | default(false) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['user_id', 'notification_type'])`

### notifications

| Column | Type | Details |
|---|---|---|
| id | `uuid` | primary |
| organization_id | `foreignId` | constrained, onDelete('cascade') |
| user_id | `foreignId` | constrained, onDelete('cascade') |
| type | `string(100)` | invoice.created, payment.received, stock.low, etc. |
| title | `string` | nullable |
| message | `text` | nullable |
| icon | `string(50)` | nullable |
| color | `string(20)` | nullable |
| action_url | `string` | nullable — URL to navigate to |
| action_text | `string(50)` | nullable — Button text |
| notifiable_type | `string` | nullable — Related model class |
| notifiable_id | `unsignedBigInteger` | nullable — Related model ID |
| data | `json` | nullable — Additional data |
| channel | `string(20)` | default('database') — database, email, sms, push |
| read_at | `timestamp` | nullable |
| sent_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['user_id', 'read_at'])`
- `$table->index(['organization_id', 'type'])`
- `$table->index(['notifiable_type', 'notifiable_id'])`
- `$table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'notifications_notifiable_read_at_index')`

### organization_module_access

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| module_id | `foreignId` | constrained('module_definitions'), cascadeOnDelete |
| is_enabled | `boolean` | default(true) |
| enabled_at | `date` | nullable |
| disabled_at | `date` | nullable |
| enabled_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| config | `json` | nullable — Module-specific config overrides |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'module_id'])`

### organization_modules

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, onDelete('cascade') |
| module_code | `string(50)` |  |
| is_enabled | `boolean` | default(true) |
| enabled_features | `json` | nullable — Specific features within module |
| settings | `json` | nullable — Module-specific settings |
| enabled_at | `timestamp` | nullable |
| disabled_at | `timestamp` | nullable |
| enabled_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'module_code'])`
- `$table->index(['organization_id', 'is_enabled'])`

### recurring_profiles

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| branch_id | `foreignId` | nullable, constrained, nullOnDelete |
| name | `string(100)` |  |
| profile_type | `string(30)` | invoice, bill, journal_entry, expense |
| source_type | `string(100)` | Invoice, Bill, JournalEntry |
| source_id | `unsignedBigInteger` |  |
| frequency | `string(20)` | daily, weekly, monthly, quarterly, yearly, custom |
| interval | `unsignedSmallInteger` | default(1) — every X frequency |
| schedule_config | `json` | nullable — Day of week, day of month, etc. |
| start_date | `date` |  |
| end_date | `date` | nullable — null = no end |
| next_run_date | `date` | nullable |
| last_run_date | `date` | nullable |
| max_occurrences | `unsignedInteger` | nullable |
| occurrences_count | `unsignedInteger` | default(0) |
| auto_send | `boolean` | default(false) — Auto-send/post after creation |
| send_reminder | `boolean` | default(false) |
| reminder_days_before | `unsignedSmallInteger` | default(3) |
| status | `string(20)` | default('active') — active, paused, completed, expired |
| notify_on_creation | `boolean` | default(true) |
| notify_email | `string(255)` | nullable |
| created_by | `foreignId` | constrained('users'), cascadeOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'status', 'next_run_date'])`

### recurring_profile_logs

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| recurring_profile_id | `foreignId` | constrained, cascadeOnDelete |
| created_type | `string(100)` | The type of document created |
| created_id | `unsignedBigInteger` | The ID of created document |
| scheduled_date | `date` |  |
| created_date | `date` |  |
| status | `string(20)` | default('success') — success, failed, skipped |
| error_message | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['recurring_profile_id', 'status'])`

Added by later migrations:

- `0510_recurring_profile_log_created_nullable.php`: `$table->string('created_type', 100)->nullable()->change()`
- `0510_recurring_profile_log_created_nullable.php`: `$table->unsignedBigInteger('created_id')->nullable()->change()`

### sensitive_access_logs

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | nullable, constrained('organizations'), nullOnDelete |
| user_id | `foreignId` | constrained('users'), cascadeOnDelete |
| model_type | `string(100)` |  |
| model_id | `unsignedBigInteger` |  |
| action | `string(20)` | default('read') |
| sensitive_fields | `string(500)` | nullable |
| ip_address | `string(45)` | nullable |
| user_agent | `string(500)` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['model_type', 'model_id'], 'sal_model_idx')`
- `$table->index(['user_id', 'created_at'], 'sal_user_date_idx')`
- `$table->index(['organization_id', 'created_at'], 'sal_org_date_idx')`

## 0050_core_3.php

### sso_sessions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| user_id | `unsignedBigInteger` |  |
| provider_id | `unsignedBigInteger` |  |
| external_user_id | `string` |  |
| access_token_hash | `string` | nullable |
| id_token_hash | `string` | nullable |
| session_started_at | `timestamp` |  |
| last_activity_at | `timestamp` |  |
| expires_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete()`
- `$table->foreign('user_id', 'sso_sess_usr_fk')->references('id')->on('users')->cascadeOnDelete()`
- `$table->foreign('provider_id', 'sso_sess_prov_fk')->references('id')->on('sso_providers')->cascadeOnDelete()`

### user_events

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | nullable, constrained, nullOnDelete |
| user_id | `foreignId` | nullable, constrained, nullOnDelete |
| event_type | `string(100)` | index |
| payload | `json` | nullable |
| ip_address | `string(45)` | nullable |
| user_agent | `string` | nullable |
| created_at | `timestamp` | useCurrent |

Indexes:

- `$table->index(['organization_id', 'event_type', 'created_at'])`

### user_module_overrides

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| user_id | `foreignId` | constrained, cascadeOnDelete |
| module_id | `foreignId` | constrained('module_definitions'), cascadeOnDelete |
| override_type | `string(20)` | grant, revoke, restrict |
| can_view | `boolean` | nullable |
| can_create | `boolean` | nullable |
| can_edit | `boolean` | nullable |
| can_delete | `boolean` | nullable |
| can_export | `boolean` | nullable |
| can_import | `boolean` | nullable |
| can_approve | `boolean` | nullable |
| data_scope | `string(30)` | nullable |
| max_amount_limit | `decimal(15, 2)` | nullable |
| custom_permissions | `json` | nullable |
| reason | `text` | nullable — Why this override was set |
| expires_at | `date` | nullable — Temporary access |
| granted_by | `foreignId` | constrained('users'), cascadeOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['user_id', 'module_id'])`

### user_onboarding_progress

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| user_id | `foreignId` | constrained, cascadeOnDelete |
| template_id | `foreignId` | constrained('onboarding_templates'), cascadeOnDelete |
| step_id | `foreignId` | constrained('onboarding_steps'), cascadeOnDelete |
| completed_at | `timestamp` | nullable |
| skipped_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['user_id', 'template_id', 'step_id'], 'uop_user_template_step_unique')`
- `$table->index(['organization_id', 'user_id', 'template_id'], 'uop_org_user_template_idx')`

### user_preferences

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| user_id | `foreignId` | constrained, cascadeOnDelete |
| key | `string(100)` |  |
| value | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['user_id', 'key'])`

### user_sessions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| user_id | `foreignId` | constrained, cascadeOnDelete |
| session_id | `string(100)` | unique |
| ip_address | `string(45)` |  |
| user_agent | `string` | nullable |
| device_type | `string(20)` | nullable — desktop, mobile, tablet |
| browser | `string(50)` | nullable |
| os | `string(50)` | nullable |
| location | `string` | nullable — City, Country from IP |
| login_at | `timestamp` |  |
| last_activity_at | `timestamp` |  |
| logout_at | `timestamp` | nullable |
| is_active | `boolean` | default(true) |
| logout_reason | `string(50)` | nullable — manual, expired, forced |

Indexes:

- `$table->index(['user_id', 'is_active'])`
- `$table->index(['session_id'])`

### webhook_events

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| event_type | `string(100)` |  |
| resource_type | `string(100)` | invoice, payment, customer, etc. |
| resource_id | `string(100)` | nullable |
| data | `json` | Event payload |
| webhooks_triggered | `unsignedInteger` | default(0) |
| created_at | `timestamp` |  |

Indexes:

- `$table->index(['organization_id', 'event_type'])`
- `$table->index(['organization_id', 'resource_type', 'resource_id'])`
- `$table->index('created_at')`

### webhooks

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| created_by | `foreignId` | constrained('users'), cascadeOnDelete |
| name | `string` |  |
| url | `string` |  |
| secret | `string(64)` | nullable — For HMAC signature |
| events | `json` | Array of event types to subscribe to |
| headers | `json` | nullable — Custom headers to include |
| is_active | `boolean` | default(true) |
| retry_count | `unsignedTinyInteger` | default(3) |
| timeout_seconds | `unsignedInteger` | default(30) |
| content_type | `string(50)` | default('application/json') |
| last_triggered_at | `timestamp` | nullable |
| last_success_at | `timestamp` | nullable |
| last_failure_at | `timestamp` | nullable |
| success_count | `unsignedInteger` | default(0) |
| failure_count | `unsignedInteger` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'is_active'])`

### webhook_deliveries

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| webhook_id | `foreignId` | constrained, cascadeOnDelete |
| event_type | `string(100)` |  |
| payload | `json` |  |
| status | `string(20)` | default('pending') — pending, success, failed |
| http_status | `unsignedSmallInteger` | nullable |
| response_body | `text` | nullable |
| response_headers | `json` | nullable |
| duration_ms | `unsignedInteger` | nullable — Response time |
| attempt | `unsignedTinyInteger` | default(1) |
| next_retry_at | `timestamp` | nullable |
| error_message | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['webhook_id', 'status'])`
- `$table->index(['webhook_id', 'event_type'])`
- `$table->index('created_at')`
- `$table->index('next_retry_at')`
- `$table->index(['status', 'created_at'], 'webhook_deliveries_status_created_at_index')`

### webhook_dlq_entries

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| webhook_id | `unsignedBigInteger` |  |
| event_type | `string` |  |
| payload | `json` |  |
| failure_count | `unsignedTinyInteger` | default(1) |
| first_failed_at | `timestamp` |  |
| last_failed_at | `timestamp` |  |
| last_error | `text` | nullable |
| next_retry_at | `timestamp` | nullable |
| status | `enum(['pending', 'retrying', 'dead', 'replayed'])` | default('pending') |
| replayed_at | `timestamp` | nullable |
| replayed_by | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'status'])`

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete()`
- `$table->foreign('webhook_id', 'wh_dlq_wh_fk')->references('id')->on('webhooks')->cascadeOnDelete()`
- `$table->foreign('replayed_by', 'wh_dlq_usr_fk')->references('id')->on('users')->nullOnDelete()`

### workflow_escalation_logs

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained('organizations'), name('wel_org_fk') |
| approval_request_id | `foreignId` | constrained('approval_requests'), name('wel_request_fk') |
| workflow_escalation_rule_id | `foreignId` | constrained('workflow_escalation_rules'), name('wel_rule_fk') |
| escalation_type | `string(50)` |  |
| triggered_at | `dateTime` |  |
| escalated_to_user_id | `foreignId` | nullable, constrained('users'), name('wel_escalated_to_fk') |
| action_taken | `string(100)` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['approval_request_id'], 'wel_request_idx')`

### workflow_escalation_rules

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations') |
| approval_workflow_id | `foreignId` | nullable, constrained('approval_workflows'), name('wer_workflow_fk') |
| step_number | `unsignedSmallInteger` | nullable |
| escalation_type | `enum(['reminder', 'escalate_to_manager', 'escalate_to_admin', 'auto_approve', 'auto_reject', ])` | default('reminder') |
| trigger_after_hours | `unsignedSmallInteger` |  |
| escalate_to_user_id | `foreignId` | nullable, constrained('users'), name('wer_escalate_to_fk') |
| escalate_to_role | `string(100)` | nullable |
| notification_template | `text` | nullable |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['approval_workflow_id'], 'wer_workflow_idx')`

### workflow_substitution_rules

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), name('wsr_org_fk') |
| approver_id | `foreignId` | constrained('users'), name('wsr_approver_fk') |
| substitute_id | `foreignId` | constrained('users'), name('wsr_substitute_fk') |
| valid_from | `date` |  |
| valid_to | `date` | nullable |
| is_active | `boolean` | default(true) |
| reason | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['approver_id', 'is_active'], 'wsr_approver_active_idx')`

## 0060_accounting_2.php

### accounting_account_groups

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| code | `string(10)` |  |
| name | `string(100)` |  |
| account_category | `enum(['balance_sheet', 'profit_loss', 'statistical'])` |  |
| number_range_from | `string(20)` | nullable |
| number_range_to | `string(20)` | nullable |
| reconciliation_account | `boolean` | default(false) |
| reconciliation_type | `enum(['customer', 'vendor', 'asset', 'none'])` | default('none') |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'code'])`

### accounting_document_types

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| code | `string(10)` |  |
| name | `string(100)` |  |
| account_type | `enum(['asset', 'liability', 'revenue', 'expense', 'equity', 'all'])` | default('all') |
| number_range_code | `string(20)` | nullable |
| reverse_document_type | `boolean` | default(false) |
| reverse_document_type_code | `string(10)` | nullable |
| require_reference | `boolean` | default(false) |
| check_duplicate_invoice | `boolean` | default(false) |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'code'])`
- `$table->index('organization_id')`

### bank_guarantees

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations') |
| guarantee_number | `string(50)` |  |
| guarantee_type | `enum(['bid_bond', 'performance_bond', 'advance_payment', 'retention', 'financial'])` | default('performance_bond') |
| direction | `enum(['issued', 'received'])` | default('issued') |
| bank_id | `foreignId` | nullable, constrained('contacts'), name('bg_bank_fk') |
| beneficiary_id | `foreignId` | nullable, constrained('contacts'), name('bg_beneficiary_fk') |
| applicant_id | `foreignId` | nullable, constrained('contacts'), name('bg_applicant_fk') |
| related_purchase_order_id | `foreignId` | nullable, constrained('purchase_orders'), name('bg_po_fk') |
| related_sales_order_id | `foreignId` | nullable, constrained('sales_orders'), name('bg_so_fk') |
| currency_code | `char(3)` | default('SAR') |
| amount | `decimal(18, 4)` |  |
| bank_charges | `decimal(18, 4)` | default(0) |
| issue_date | `date` |  |
| expiry_date | `date` | nullable |
| claim_deadline | `date` | nullable |
| status | `enum(['draft', 'active', 'expired', 'claimed', 'returned', 'cancelled'])` | default('draft') |
| is_auto_renewed | `boolean` | default(false) |
| renewal_period_days | `unsignedSmallInteger` | nullable |
| claim_amount | `decimal(18, 4)` | nullable |
| claim_date | `date` | nullable |
| claim_reason | `text` | nullable |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'guarantee_number'], 'bg_org_number_unq')`
- `$table->index(['organization_id', 'status'], 'bg_org_status_idx')`
- `$table->index(['expiry_date'], 'bg_expiry_idx')`

### cash_flow_scenarios

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string(36)` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| name | `string(100)` |  |
| description | `text` | nullable |
| is_base_case | `boolean` | default(false) |
| assumptions | `json` | nullable |
| created_by | `foreignId` | constrained('users') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'is_base_case'], 'cash_flow_scenarios_org_base_idx')`

### cash_flow_forecasts

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string(36)` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| forecast_date | `date` |  |
| horizon_days | `tinyInteger` | unsigned, default(90) |
| currency_code | `string(3)` | default('SAR') |
| total_opening_balance | `decimal(15, 4)` | default(0) |
| total_inflows | `decimal(15, 4)` | default(0) |
| total_outflows | `decimal(15, 4)` | default(0) |
| closing_balance | `decimal(15, 4)` | default(0) |
| scenario_id | `foreignId` | nullable, constrained('cash_flow_scenarios'), nullOnDelete |
| generated_at | `timestamp` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'forecast_date'], 'cash_flow_forecasts_org_date_idx')`
- `$table->index(['organization_id', 'scenario_id'], 'cash_flow_forecasts_org_scen_idx')`

### cash_flow_forecast_lines

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| cash_flow_forecast_id | `foreignId` | constrained('cash_flow_forecasts'), cascadeOnDelete |
| expected_date | `date` |  |
| line_type | `enum(['inflow', 'outflow'])` | default('inflow') |
| category | `string(100)` | nullable |
| description | `string(500)` | nullable |
| source_type | `string(50)` | nullable |
| source_id | `unsignedBigInteger` | nullable |
| expected_amount | `decimal(15, 2)` | default(0) |
| actual_amount | `decimal(15, 2)` | nullable |
| is_confirmed | `boolean` | default(false) |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['cash_flow_forecast_id', 'expected_date'], 'cff_lines_forecast_date_idx')`
- `$table->index(['cash_flow_forecast_id', 'line_type'], 'cff_lines_forecast_type_idx')`
- `$table->index(['source_type', 'source_id'], 'cff_lines_source_idx')`

### cash_flow_lines

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| forecast_id | `foreignId` | constrained('cash_flow_forecasts'), cascadeOnDelete |
| expected_date | `date` |  |
| flow_type | `enum(['inflow', 'outflow'])` |  |
| source_type | `string(50)` |  |
| source_id | `unsignedBigInteger` | nullable |
| description | `string(200)` |  |
| amount | `decimal(15, 4)` |  |
| confidence | `enum(['certain', 'probable', 'possible'])` | default('probable') |
| is_actual | `boolean` | default(false) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['forecast_id', 'expected_date'], 'cash_flow_lines_forecast_date_idx')`
- `$table->index(['forecast_id', 'flow_type'], 'cash_flow_lines_forecast_type_idx')`
- `$table->index(['source_type', 'source_id'], 'cash_flow_lines_source_idx')`

### chart_of_accounts

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| parent_id | `foreignId` | nullable, constrained('chart_of_accounts'), nullOnDelete |
| code | `string(20)` |  |
| name | `string(100)` |  |
| description | `text` | nullable |
| account_type | `enum(['asset', 'liability', 'equity', 'income', 'expense', ])` |  |
| sub_type | `enum(['cash', 'bank', 'receivable', 'inventory', 'fixed_asset', 'other_asset', 'payable', 'credit_card', 'tax_payable', 'other_liability', 'capital', 'retained_earnings', 'drawings', 'sales', 'other_income', 'cost_of_goods', 'operating_expense', 'other_expense', ])` |  |
| currency_code | `string(3)` | nullable — If account is in specific currency |
| is_active | `boolean` | default(true) |
| is_system | `boolean` | default(false) — System accounts can't be deleted |
| is_header | `boolean` | default(false) — Header accounts can't have transactions |
| level | `unsignedInteger` | default(1) |
| path | `string(255)` | nullable — e.g., "1.2.3" for hierarchy |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'code'])`
- `$table->index(['organization_id', 'account_type'])`
- `$table->index(['organization_id', 'sub_type'])`
- `$table->index(['organization_id', 'parent_id'])`

Foreign keys:

- `$table->foreign('currency_code')->references('code')->on('currencies')->nullOnDelete()`

### account_balance_snapshots

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| account_id | `foreignId` | constrained('chart_of_accounts'), cascadeOnDelete |
| balance | `decimal(20, 4)` | default(0) |
| debit_total | `decimal(20, 4)` | default(0) |
| credit_total | `decimal(20, 4)` | default(0) |
| computed_at | `timestamp` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'account_id'])`
- `$table->index(['organization_id', 'computed_at'])`

### accrual_deferrals

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string(36)` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| reference | `string(50)` |  |
| type | `enum(['accrual', 'deferral'])` |  |
| debit_account_id | `foreignId` | constrained('chart_of_accounts') |
| credit_account_id | `foreignId` | constrained('chart_of_accounts') |
| total_amount | `decimal(15, 4)` |  |
| per_period_amount | `decimal(15, 4)` |  |
| currency_code | `string(3)` | default('SAR') |
| start_date | `date` |  |
| end_date | `date` |  |
| periods_total | `integer` |  |
| periods_posted | `integer` | default(0) |
| status | `enum(['active', 'completed', 'cancelled'])` | default('active') |
| description | `text` | nullable |
| created_by | `foreignId` | constrained('users') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'status'], 'ad_org_status_idx')`
- `$table->index(['organization_id', 'start_date', 'end_date'], 'ad_org_dates_idx')`

### asset_categories

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| name | `string` |  |
| code | `string` |  |
| description | `text` | nullable |
| default_useful_life_years | `unsignedSmallInteger` | default(5) |
| default_depreciation_method | `enum(['straight_line', 'declining_balance', 'units_of_production', 'sum_of_years_digits', ])` | default('straight_line') |
| default_salvage_percent | `decimal(5, 2)` | default(0) |
| gl_asset_account_id | `unsignedBigInteger` | nullable |
| gl_depreciation_account_id | `unsignedBigInteger` | nullable |
| gl_accumulated_account_id | `unsignedBigInteger` | nullable |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'code'])`

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete()`
- `$table->foreign('gl_asset_account_id')->references('id')->on('chart_of_accounts')->nullOnDelete()`
- `$table->foreign('gl_depreciation_account_id')->references('id')->on('chart_of_accounts')->nullOnDelete()`
- `$table->foreign('gl_accumulated_account_id')->references('id')->on('chart_of_accounts')->nullOnDelete()`

### bank_accounts

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| branch_id | `foreignId` | nullable, constrained, nullOnDelete |
| bank_name | `string(100)` |  |
| account_name | `string(100)` |  |
| account_number | `string(50)` |  |
| iban | `string(50)` | nullable |
| swift_code | `string(20)` | nullable |
| branch_name | `string(100)` | nullable |
| branch_code | `string(20)` | nullable |
| currency_code | `string(3)` |  |
| account_type | `enum(['current', 'savings', 'credit_card', 'cash'])` | default('current') |
| gl_account_id | `foreignId` | nullable, constrained('chart_of_accounts'), nullOnDelete |
| current_balance | `decimal(18, 4)` | default(0) |
| last_reconciled_date | `date` | nullable |
| last_reconciled_balance | `decimal(18, 4)` | nullable |
| bank_balance | `decimal(15, 2)` | default(0) — Last known bank balance |
| is_default | `boolean` | default(false) |
| is_active | `boolean` | default(true) |
| settings | `json` | nullable — Bank-specific settings |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'account_number', 'bank_name'])`
- `$table->index(['organization_id', 'is_active'])`

### bank_account_requests

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| bank_account_id | `foreignId` | nullable, constrained('bank_accounts'), nullOnDelete |
| request_type | `enum(['open', 'close', 'modify', 'add_signatory', 'remove_signatory'])` | default('open') |
| status | `enum(['pending', 'approved', 'rejected', 'executed'])` | default('pending') |
| bank_name | `string` | nullable |
| account_name | `string` | nullable |
| account_type | `string(30)` | nullable |
| currency_code | `string(3)` | nullable |
| iban | `string(34)` | nullable |
| swift_code | `string(11)` | nullable |
| branch_name | `string` | nullable |
| request_data | `json` | nullable |
| justification | `text` | nullable |
| requested_by | `foreignId` | constrained('users') |
| approved_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| rejected_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| approval_notes | `text` | nullable |
| rejection_reason | `text` | nullable |
| approved_at | `timestamp` | nullable |
| rejected_at | `timestamp` | nullable |
| executed_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'status'])`
- `$table->index(['organization_id', 'request_type'])`

### bank_matching_rules

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| bank_account_id | `foreignId` | nullable, constrained, nullOnDelete |
| name | `string` |  |
| match_field | `string(30)` | description, reference, amount |
| match_type | `string(20)` | contains, starts_with, equals, regex |
| match_value | `string` |  |
| transaction_type | `string(10)` | nullable — debit, credit |
| action | `string(30)` | categorize, match_contact, match_account, exclude |
| action_data | `json` | Category, account_id, contact_id, etc. |
| priority | `unsignedInteger` | default(0) |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'is_active'])`

### bank_positions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| bank_account_id | `foreignId` | constrained('bank_accounts'), cascadeOnDelete |
| position_date | `date` |  |
| book_balance | `decimal(15, 4)` | default(0) |
| available_balance | `decimal(15, 4)` | default(0) |
| projected_balance | `decimal(15, 4)` | default(0) |
| currency_code | `string(3)` | default('SAR') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'bank_account_id', 'position_date'], 'bp_org_acct_date_unique')`

### bank_reconciliations

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| bank_account_id | `foreignId` | constrained, cascadeOnDelete |
| statement_date | `date` |  |
| statement_balance | `decimal(15, 2)` |  |
| book_balance | `decimal(15, 2)` |  |
| adjusted_book_balance | `decimal(15, 2)` | nullable |
| difference | `decimal(15, 2)` | default(0) |
| status | `string(20)` | default('in_progress') — in_progress, completed, cancelled |
| summary | `json` | nullable — Reconciliation summary |
| created_by | `foreignId` | constrained('users'), cascadeOnDelete |
| completed_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| completed_at | `timestamp` | nullable |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['bank_account_id', 'status'])`

### bank_signatories

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| bank_account_id | `foreignId` | constrained('bank_accounts'), cascadeOnDelete |
| user_id | `foreignId` | nullable, constrained('users'), nullOnDelete |
| name | `string` |  |
| title | `string` | nullable |
| email | `string` | nullable |
| phone | `string(30)` | nullable |
| authority_level | `enum(['single', 'joint_any', 'joint_all'])` | default('single') |
| signing_limit | `decimal(18, 4)` | nullable |
| valid_from | `date` |  |
| valid_to | `date` | nullable |
| is_active | `boolean` | default(true) |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'bank_account_id'])`
- `$table->index(['bank_account_id', 'is_active'])`

### bank_statement_imports

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| bank_account_id | `foreignId` | constrained, cascadeOnDelete |
| user_id | `foreignId` | constrained, cascadeOnDelete |
| file_name | `string` |  |
| file_path | `string` | nullable |
| file_type | `string(10)` | csv, ofx, qfx, mt940 |
| statement_start_date | `date` | nullable |
| statement_end_date | `date` | nullable |
| total_transactions | `unsignedInteger` | default(0) |
| imported_transactions | `unsignedInteger` | default(0) |
| duplicate_transactions | `unsignedInteger` | default(0) |
| status | `string(20)` | default('pending') — pending, processing, completed, failed |
| errors | `json` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['bank_account_id', 'created_at'])`
- `$table->index(['organization_id', 'status'], 'bsi_org_status_idx')`
- `$table->index(['bank_account_id'], 'bsi_account_idx')`

### bank_transactions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| bank_account_id | `foreignId` | constrained, cascadeOnDelete |
| transaction_date | `date` |  |
| value_date | `date` | nullable |
| reference | `string(100)` | nullable |
| description | `text` |  |
| transaction_type | `string(20)` | debit, credit |
| amount | `decimal(15, 2)` |  |
| balance | `decimal(15, 2)` | nullable — Running balance from bank |
| status | `string(20)` | default('unmatched') — unmatched, matched, excluded, reconciled |
| category | `string` | nullable — Auto-categorized |
| matched_transaction_id | `unsignedBigInteger` | nullable |
| matched_transaction_type | `string` | nullable |
| matched_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| matched_at | `timestamp` | nullable |
| import_source | `string(50)` | nullable — manual, csv, ofx, api |
| import_batch_id | `string` | nullable |
| raw_data | `json` | nullable — Original import data |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['bank_account_id', 'transaction_date'])`
- `$table->index(['bank_account_id', 'status'])`
- `$table->index(['organization_id', 'status'])`

### bank_reconciliation_items

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| reconciliation_id | `foreignId` | constrained('bank_reconciliations'), cascadeOnDelete |
| bank_transaction_id | `foreignId` | nullable, constrained('bank_transactions'), nullOnDelete |
| item_type | `string(30)` | bank_transaction, outstanding_check, outstanding_deposit, adjustment |
| transaction_date | `date` |  |
| reference | `string` | nullable |
| description | `text` |  |
| amount | `decimal(15, 2)` |  |
| is_cleared | `boolean` | default(false) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['reconciliation_id', 'is_cleared'])`

### check_books

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations') |
| bank_account_id | `foreignId` | constrained('bank_accounts'), name('cb_bank_account_fk') |
| check_book_number | `string(50)` |  |
| from_check_number | `string(20)` |  |
| to_check_number | `string(20)` |  |
| current_check_number | `string(20)` |  |
| status | `enum(['active', 'exhausted', 'cancelled'])` | default('active') |
| issued_date | `date` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'check_book_number'], 'cb_org_number_unq')`

### distribution_cycles

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string(36)` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| name | `string(150)` |  |
| fiscal_year | `unsignedSmallInteger` |  |
| period_from | `tinyInteger` | unsigned, default(1) |
| period_to | `tinyInteger` | unsigned, default(12) |
| status | `enum(['open', 'executed', 'reversed'])` | default('open') |
| executed_at | `timestamp` | nullable |
| executed_by | `foreignId` | nullable, constrained('users', 'id', 'co_dist_cyc_exec_by_fk'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'fiscal_year', 'status'], 'co_dist_cyc_org_fy_status_idx')`

### collections_worklist

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string(36)` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| contact_id | `unsignedBigInteger` |  |
| total_overdue | `decimal(15, 4)` | default(0) |
| overdue_days_max | `integer` | default(0) |
| collections_status | `enum(['new', 'contacted', 'promise_to_pay', 'payment_plan', 'legal', 'written_off'])` | default('new') |
| promise_to_pay_date | `date` | nullable |
| promise_amount | `decimal(15, 4)` | default(0) |
| assigned_to | `foreignId` | nullable, constrained('users'), nullOnDelete |
| last_contact_at | `timestamp` | nullable |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'collections_status'], 'cw_org_status_idx')`
- `$table->index(['organization_id', 'contact_id'], 'cw_org_contact_idx')`

### consolidation_groups

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| name | `string` |  |
| description | `text` | nullable |
| currency_code | `string(3)` | default('SAR') |
| is_active | `boolean` | default(true) |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('organization_id')`

### consolidation_entities

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| consolidation_group_id | `foreignId` | constrained('consolidation_groups'), cascadeOnDelete |
| entity_organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| name | `string` |  |
| ownership_percent | `decimal(5, 2)` | default(100.00) |
| consolidation_method | `enum(['full', 'proportional', 'equity'])` | default('full') |
| local_currency | `string(3)` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['consolidation_group_id', 'entity_organization_id'], 'consol_ent_group_entity_org_unique')`

### copa_dimensions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| dimension_type | `string(50)` | product, customer, region, sales_channel, material_group |
| dimension_value | `string(100)` |  |
| dimension_label | `string(200)` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'dimension_type'], 'copa_dim_org_type_idx')`

### cost_elements

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string(36)` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| code | `string(20)` |  |
| name | `string(150)` |  |
| element_type | `enum(['primary', 'secondary'])` | primary=GL mapped, secondary=internal |
| gl_account_id | `foreignId` | nullable, constrained('chart_of_accounts'), nullOnDelete |
| cost_element_category | `enum(['general', 'depreciation', 'imputed', 'revenue', 'internal_settlement'])` | default('general') |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'code'], 'ce_org_code_unique')`

### activity_types

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string(36)` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| code | `string(20)` |  |
| name | `string(150)` |  |
| unit_of_measure | `string(20)` | default('HR') — HR=hours, PC=pieces |
| cost_element_id | `foreignId` | nullable, constrained('cost_elements'), nullOnDelete |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'code'], 'at_org_code_unique')`

### cost_repostings

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| reposting_number | `string(50)` |  |
| posting_date | `date` |  |
| document_date | `date` |  |
| period | `unsignedTinyInteger` | 1–12 |
| fiscal_year | `unsignedSmallInteger` |  |
| from_type | `enum(['cost_center', 'internal_order', 'profit_center'])` |  |
| from_id | `unsignedBigInteger` |  |
| to_type | `enum(['cost_center', 'internal_order', 'profit_center'])` |  |
| to_id | `unsignedBigInteger` |  |
| cost_element_id | `foreignId` | constrained('cost_elements') |
| amount | `decimal(15, 4)` |  |
| currency_code | `char(3)` | default('SAR') |
| narration | `text` | nullable |
| status | `enum(['posted', 'reversed'])` | default('posted') |
| reversed_by_id | `foreignId` | nullable, constrained('cost_repostings'), nullOnDelete |
| posted_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| reversed_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'reposting_number'])`

### cost_splitting_rules

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations') |
| cost_center_id | `foreignId` | constrained('cost_centers'), name('csr_cc_fk') |
| cost_element_id | `foreignId` | nullable, constrained('cost_elements'), name('csr_ce_fk') |
| fixed_percentage | `decimal(5, 2)` |  |
| variable_percentage | `decimal(5, 2)` |  |
| splitting_basis | `enum(['activity_quantity', 'capacity_utilization', 'manual'])` | default('manual') |
| is_active | `boolean` | default(true) |
| valid_from | `date` |  |
| valid_to | `date` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'cost_center_id'], 'csr_org_cc_idx')`

### costing_sheets

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| code | `string(30)` |  |
| name | `string` |  |
| description | `text` | nullable |
| cost_component_structure_id | `bigInteger` | nullable |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'code'], 'cs_org_code_uq')`
- `$table->index(['organization_id', 'is_active'], 'cs_org_active_idx')`

Foreign keys:

- `$table->foreign('organization_id', 'cs_org_fk')->references('id')->on('organizations')->onDelete('cascade')`

### costing_sheet_runs

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| costing_sheet_id | `unsignedBigInteger` |  |
| reference_type | `string(50)` |  |
| reference_id | `unsignedBigInteger` |  |
| run_date | `dateTime` |  |
| total_overhead | `decimal(18, 4)` | default(0) |
| currency_code | `string(3)` |  |
| status | `string(20)` | default('pending') |
| error_message | `text` | nullable |
| created_by | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id', 'csrun_org_fk')->references('id')->on('organizations')->onDelete('cascade')`
- `$table->foreign('costing_sheet_id', 'csrun_cs_fk')->references('id')->on('costing_sheets')->onDelete('cascade')`
- `$table->foreign('created_by', 'csrun_user_fk')->references('id')->on('users')->onDelete('set null')`

### direct_debit_mandates

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations') |
| mandate_reference | `string(50)` |  |
| mandate_type | `enum(['core', 'b2b', 'standing_order'])` | default('core') |
| direction | `enum(['collection', 'payment'])` | default('collection') |
| counterparty_id | `foreignId` | constrained('contacts'), name('ddm_counterparty_fk') |
| bank_account_id | `foreignId` | nullable, constrained('bank_accounts'), name('ddm_bank_account_fk') |
| iban | `string(34)` | nullable |
| bic | `string(11)` | nullable |
| currency_code | `char(3)` | default('SAR') |
| amount | `decimal(18, 4)` | nullable |
| frequency | `enum(['weekly', 'biweekly', 'monthly', 'quarterly', 'annually', 'one_time'])` | default('monthly') |
| first_collection_date | `date` | nullable |
| next_collection_date | `date` | nullable |
| last_collection_date | `date` | nullable |
| total_collections | `unsignedInteger` | default(0) |
| max_collections | `unsignedInteger` | nullable |
| status | `enum(['draft', 'active', 'paused', 'cancelled', 'expired'])` | default('draft') |
| signed_date | `date` | nullable |
| cancellation_date | `date` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'mandate_reference'], 'ddm_org_ref_unq')`
- `$table->index(['organization_id', 'status', 'next_collection_date'], 'ddm_org_status_next_idx')`

### dispute_cases

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string(36)` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| case_number | `string(30)` |  |
| document_type | `enum(['invoice', 'payment_received', 'credit_note'])` |  |
| document_id | `unsignedBigInteger` |  |
| contact_id | `unsignedBigInteger` |  |
| disputed_amount | `decimal(15, 4)` |  |
| resolved_amount | `decimal(15, 4)` | default(0) |
| dispute_reason | `enum(['pricing', 'quality', 'quantity', 'delivery', 'duplicate', 'other'])` | default('other') |
| description | `text` | nullable |
| status | `enum(['open', 'in_review', 'escalated', 'resolved', 'closed'])` | default('open') |
| assigned_to | `foreignId` | nullable, constrained('users'), nullOnDelete |
| due_date | `date` | nullable |
| resolution_notes | `text` | nullable |
| created_by | `foreignId` | constrained('users') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'case_number'], 'dc_org_case_unique')`
- `$table->index(['organization_id', 'status'], 'dc_org_status_idx')`
- `$table->index(['contact_id', 'status'], 'dc_contact_status_idx')`

### document_splitting_rules

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| name | `string(100)` |  |
| split_method | `enum(['profit_center', 'segment', 'cost_center', 'business_area'])` | default('profit_center') |
| base_item_category | `string(50)` | nullable |
| is_active | `boolean` | default(true) |
| priority | `integer` | default(10) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'is_active'], 'dsr_org_active_idx')`

### dunning_levels

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string(36)` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| level_number | `tinyInteger` | unsigned |
| name | `string(100)` |  |
| days_overdue_from | `smallInteger` | unsigned |
| days_overdue_to | `smallInteger` | unsigned, nullable |
| interest_rate | `decimal(5, 2)` | default(0) |
| dunning_fee | `decimal(15, 4)` | default(0) |
| is_legal_action | `boolean` | default(false) |
| email_template_id | `foreignId` | nullable, constrained('import_templates'), nullOnDelete |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'level_number'], 'dunning_levels_org_level_unique')`
- `$table->index(['organization_id', 'is_active'], 'dunning_levels_org_active_idx')`

### dunning_runs

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string(36)` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| run_date | `date` |  |
| status | `enum(['draft', 'posted', 'cancelled'])` | default('draft') |
| total_customers | `unsignedInteger` | default(0) |
| total_amount | `decimal(15, 4)` | default(0) |
| created_by | `foreignId` | constrained('users') |
| posted_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'run_date'], 'dunning_runs_org_date_idx')`
- `$table->index(['organization_id', 'status'], 'dunning_runs_org_status_idx')`

### exchange_rates

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| from_currency | `string(3)` |  |
| to_currency | `string(3)` |  |
| rate | `decimal(18, 8)` |  |
| rate_date | `date` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'from_currency', 'to_currency', 'rate_date'], 'exchange_rate_unique')`
- `$table->index(['organization_id', 'rate_date'])`

Foreign keys:

- `$table->foreign('from_currency')->references('code')->on('currencies')`
- `$table->foreign('to_currency')->references('code')->on('currencies')`

### financial_close_templates

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string` | unique |
| organization_id | `unsignedBigInteger` |  |
| name | `string` |  |
| description | `text` | nullable |
| close_type | `string(20)` |  |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id', 'fk_fct_org')->references('id')->on('organizations')->onDelete('cascade')`

### financial_close_periods

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string` | unique |
| organization_id | `unsignedBigInteger` |  |
| financial_close_template_id | `unsignedBigInteger` | nullable |
| fiscal_year | `smallInteger` |  |
| period | `tinyInteger` |  |
| close_type | `string(20)` |  |
| status | `string(20)` | default('open') |
| opened_at | `dateTime` | nullable |
| closed_at | `dateTime` | nullable |
| closed_by | `unsignedBigInteger` | nullable |
| due_date | `date` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |
| signed_off_by | `unsignedBigInteger` | nullable |
| signed_off_at | `dateTime` | nullable |
| sign_off_notes | `text` | nullable |

Indexes:

- `$table->index(['organization_id', 'fiscal_year', 'period'], 'idx_fcp_org_period')`

Foreign keys:

- `$table->foreign('organization_id', 'fk_fcp_org')->references('id')->on('organizations')->onDelete('cascade')`
- `$table->foreign('financial_close_template_id', 'fk_fcp_template')->references('id')->on('financial_close_templates')->onDelete('set null')`
- `$table->foreign('closed_by', 'fk_fcp_closed_by')->references('id')->on('users')->onDelete('set null')`
- `$table->foreign('signed_off_by', 'fk_fcp_signoff_user')->references('id')->on('users')->nullOnDelete()`

## 0070_accounting_3.php

### financial_close_template_tasks

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string` | unique |
| financial_close_template_id | `unsignedBigInteger` |  |
| task_name | `string` |  |
| description | `text` | nullable |
| task_type | `string(50)` |  |
| sort_order | `integer` | default(0) |
| estimated_duration_hours | `decimal(4, 1)` | nullable |
| required_role | `string(100)` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('financial_close_template_id', 'fk_fctt_template')->references('id')->on('financial_close_templates')->onDelete('cascade')`

### financial_close_tasks

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string` | unique |
| financial_close_period_id | `unsignedBigInteger` |  |
| template_task_id | `unsignedBigInteger` | nullable |
| task_name | `string` |  |
| description | `text` | nullable |
| task_type | `string(50)` |  |
| assigned_to | `unsignedBigInteger` | nullable |
| status | `string(20)` | default('pending') |
| due_date | `date` | nullable |
| started_at | `dateTime` | nullable |
| completed_at | `dateTime` | nullable |
| completed_by | `unsignedBigInteger` | nullable |
| notes | `text` | nullable |
| sort_order | `integer` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['financial_close_period_id', 'status'], 'idx_fctask_period_status')`
- `$table->index(['assigned_to', 'status'], 'idx_fctask_assignee_status')`

Foreign keys:

- `$table->foreign('financial_close_period_id', 'fk_fctask_period')->references('id')->on('financial_close_periods')->onDelete('cascade')`
- `$table->foreign('template_task_id', 'fk_fctask_tmpl_task')->references('id')->on('financial_close_template_tasks')->onDelete('set null')`
- `$table->foreign('assigned_to', 'fk_fctask_assigned')->references('id')->on('users')->onDelete('set null')`
- `$table->foreign('completed_by', 'fk_fctask_completed_by')->references('id')->on('users')->onDelete('set null')`

### financial_statement_versions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| name | `string(255)` |  |
| description | `text` | nullable |
| type | `enum(['balance_sheet', 'income_statement', 'cash_flow'])` |  |
| is_default | `boolean` | default(false) |
| is_active | `boolean` | default(true) |
| created_by | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'type', 'is_active'], 'financial_statement_versions_org_type_active_idx')`

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade')`
- `$table->foreign('created_by')->references('id')->on('users')->nullOnDelete()`

### financial_statement_version_nodes

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| fsv_id | `unsignedBigInteger` |  |
| parent_id | `unsignedBigInteger` | nullable |
| account_id | `unsignedBigInteger` | nullable |
| node_type | `enum(['header', 'account', 'total'])` |  |
| label | `string(255)` |  |
| sort_order | `unsignedSmallInteger` | default(0) |
| sign | `tinyInteger` | default(1) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['fsv_id', 'parent_id', 'sort_order'], 'fin_stmt_version_nodes_fsv_parent_sort_idx')`

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade')`
- `$table->foreign('fsv_id')->references('id')->on('financial_statement_versions')->onDelete('cascade')`
- `$table->foreign('parent_id')->references('id')->on('financial_statement_version_nodes')->nullOnDelete()`
- `$table->foreign('account_id')->references('id')->on('chart_of_accounts')->nullOnDelete()`

### fiscal_years

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| name | `string(50)` | e.g., "FY 2024-25" |
| start_date | `date` |  |
| end_date | `date` |  |
| is_current | `boolean` | default(false) |
| is_closed | `boolean` | default(false) |
| closed_at | `timestamp` | nullable |
| closed_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'name'])`
- `$table->index(['organization_id', 'is_current'])`
- `$table->index(['organization_id', 'start_date', 'end_date'])`

### account_opening_balances

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| account_id | `foreignId` | constrained('chart_of_accounts'), cascadeOnDelete |
| fiscal_year_id | `foreignId` | constrained, cascadeOnDelete |
| debit | `decimal(18, 4)` | default(0) |
| credit | `decimal(18, 4)` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['account_id', 'fiscal_year_id'])`

### accounting_periods

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| fiscal_year_id | `foreignId` | constrained, cascadeOnDelete |
| period_number | `unsignedTinyInteger` | 1-12 for months, 1-4 for quarters |
| period_type | `string(10)` | default('month') — month, quarter |
| start_date | `date` |  |
| end_date | `date` |  |
| is_closed | `boolean` | default(false) |
| closed_at | `timestamp` | nullable |
| closed_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['fiscal_year_id', 'period_number', 'period_type'], 'acct_periods_fy_num_type_unique')`

### carry_forward_runs

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string(36)` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| from_fiscal_year_id | `foreignId` | constrained('fiscal_years') |
| to_fiscal_year_id | `foreignId` | constrained('fiscal_years') |
| run_type | `enum(['balance_sheet', 'profit_loss', 'both'])` | default('both') |
| status | `enum(['pending', 'running', 'completed', 'failed'])` | default('pending') |
| accounts_processed | `integer` | default(0) |
| total_amount_carried | `decimal(15, 4)` | default(0) |
| executed_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| executed_at | `timestamp` | nullable |
| error_log | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'from_fiscal_year_id'], 'cfr_org_fy_idx')`

### consolidation_periods

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| consolidation_group_id | `foreignId` | constrained('consolidation_groups'), cascadeOnDelete |
| fiscal_year_id | `foreignId` | nullable, constrained('fiscal_years'), nullOnDelete |
| period_start | `date` |  |
| period_end | `date` |  |
| status | `enum(['open', 'in_progress', 'completed'])` | default('open') |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('organization_id')`
- `$table->index('consolidation_group_id')`

### consolidated_balances

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| consolidation_period_id | `foreignId` | constrained('consolidation_periods'), cascadeOnDelete |
| account_id | `foreignId` | constrained('chart_of_accounts') |
| entity_organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| local_amount | `decimal(15, 4)` |  |
| exchange_rate | `decimal(10, 6)` | default(1) |
| consolidated_amount | `decimal(15, 4)` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['consolidation_period_id', 'account_id', 'entity_organization_id'], 'consolidated_balances_unique')`

### copa_plan_versions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| version_name | `string(100)` |  |
| fiscal_year_id | `foreignId` | constrained('fiscal_years'), cascadeOnDelete |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'fiscal_year_id', 'version_name'], 'cpv_org_fy_name_unique')`

### depreciation_runs

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| fiscal_year_id | `unsignedBigInteger` |  |
| run_date | `date` |  |
| period_start | `date` |  |
| period_end | `date` |  |
| status | `enum(['pending', 'processing', 'posted', 'reversed', ])` | default('pending') |
| total_assets | `unsignedInteger` | default(0) |
| total_depreciation | `decimal(15, 4)` | default(0) |
| notes | `text` | nullable |
| posted_by | `unsignedBigInteger` | nullable |
| posted_at | `timestamp` | nullable |
| created_by | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete()`
- `$table->foreign('fiscal_year_id')->references('id')->on('fiscal_years')->restrictOnDelete()`
- `$table->foreign('posted_by')->references('id')->on('users')->nullOnDelete()`
- `$table->foreign('created_by')->references('id')->on('users')->nullOnDelete()`

### fixed_assets

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| branch_id | `unsignedBigInteger` | nullable |
| asset_category_id | `unsignedBigInteger` |  |
| asset_number | `string` |  |
| name | `string` |  |
| description | `text` | nullable |
| serial_number | `string` | nullable |
| location | `string` | nullable |
| status | `enum(['active', 'disposed', 'written_off', 'under_maintenance', ])` | default('active') |
| acquisition_date | `date` |  |
| acquisition_cost | `decimal(15, 4)` |  |
| salvage_value | `decimal(15, 4)` | default(0) |
| useful_life_years | `decimal(5, 2)` |  |
| depreciation_method | `enum(['straight_line', 'declining_balance', 'units_of_production', 'sum_of_years_digits', ])` | default('straight_line') |
| accumulated_depreciation | `decimal(15, 4)` | default(0) |
| book_value | `decimal(15, 4)` |  |
| last_depreciation_date | `date` | nullable |
| disposal_date | `date` | nullable |
| disposal_amount | `decimal(15, 4)` | nullable |
| disposal_reason | `string` | nullable |
| notes | `text` | nullable |
| created_by | `unsignedBigInteger` | nullable |
| deleted_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| is_auc | `boolean` | default(false), comment('Asset Under Construction flag (SAP AuC)') |
| auc_settled_amount | `decimal(15, 4)` | default(0), comment('Cumulative amount already settled to final assets') |
| auc_settled_at | `timestamp` | nullable, comment('Timestamp of final full settlement') |

Indexes:

- `$table->unique(['organization_id', 'asset_number'])`
- `$table->index(['organization_id', 'status'])`
- `$table->index(['organization_id', 'asset_category_id'])`

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete()`
- `$table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete()`
- `$table->foreign('asset_category_id')->references('id')->on('asset_categories')->restrictOnDelete()`
- `$table->foreign('created_by')->references('id')->on('users')->nullOnDelete()`

### house_banks

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| code | `string(20)` | e.g. "RIYAD", "SABB" |
| name | `string(200)` |  |
| bank_name | `string(200)` | nullable |
| bank_country | `string(3)` | nullable — ISO alpha-2/3 |
| swift_code | `string(11)` | nullable |
| routing_number | `string(50)` | nullable |
| address | `string(500)` | nullable |
| is_active | `boolean` | default(true) |
| is_default | `boolean` | default(false) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'code'])`
- `$table->index('organization_id')`

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete()`

### house_bank_accounts

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| house_bank_id | `unsignedBigInteger` |  |
| bank_account_id | `unsignedBigInteger` | nullable — optional FK to bank_accounts |
| account_id_code | `string(20)` | SAP: account ID within house bank |
| currency_code | `string(3)` | default('SAR') |
| account_purpose | `enum(['payments', 'collections', 'both'])` | default('both') |
| daily_payment_limit | `decimal(18, 4)` | nullable |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['house_bank_id', 'account_id_code'])`

Foreign keys:

- `$table->foreign('house_bank_id')->references('id')->on('house_banks')->cascadeOnDelete()`
- `$table->foreign('bank_account_id')->references('id')->on('bank_accounts')->nullOnDelete()`

### installment_plans

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| document_type | `string(30)` | invoice \| bill \| sales_order |
| document_id | `unsignedBigInteger` |  |
| contact_id | `unsignedBigInteger` | nullable |
| currency_code | `string(3)` | default('SAR') |
| total_amount | `decimal(18, 4)` |  |
| total_paid | `decimal(18, 4)` | default(0) |
| outstanding | `decimal(18, 4)` | total_amount - total_paid |
| installment_count | `integer` |  |
| status | `enum(['draft', 'active', 'completed', 'cancelled'])` | default('draft') |
| start_date | `date` |  |
| end_date | `date` | nullable |
| notes | `string(500)` | nullable |
| created_by | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'document_type', 'document_id'], 'inst_plans_doc_idx')`
- `$table->index(['organization_id', 'contact_id'])`

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete()`

### journal_entries

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| branch_id | `foreignId` | nullable, constrained, nullOnDelete |
| fiscal_year_id | `foreignId` | nullable, constrained, nullOnDelete |
| entry_number | `string(50)` |  |
| entry_date | `date` |  |
| reference | `string(100)` | nullable — External reference |
| description | `text` | nullable |
| source_type | `string(50)` | nullable — invoice, bill, payment, manual |
| source_id | `unsignedBigInteger` | nullable |
| currency_code | `string(3)` | default('SAR') |
| exchange_rate | `decimal(18, 8)` | default(1) |
| total_debit | `decimal(18, 4)` | default(0) |
| total_credit | `decimal(18, 4)` | default(0) |
| status | `enum(['draft', 'posted', 'voided'])` | default('draft') |
| posted_at | `timestamp` | nullable |
| posted_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| voided_at | `timestamp` | nullable |
| voided_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| void_reason | `string(255)` | nullable |
| reversed_by_id | `foreignId` | nullable, constrained('journal_entries'), nullOnDelete |
| reversal_of_id | `foreignId` | nullable, constrained('journal_entries'), nullOnDelete |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'entry_number'])`
- `$table->index(['organization_id', 'entry_date'])`
- `$table->index(['organization_id', 'status'])`
- `$table->index(['source_type', 'source_id'])`
- `$table->index(['organization_id', 'fiscal_year_id'])`

Foreign keys:

- `$table->foreign('currency_code')->references('code')->on('currencies')`

### asset_components

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| fixed_asset_id | `foreignId` | constrained('fixed_assets'), cascadeOnDelete |
| component_number | `string(50)` |  |
| name | `string` |  |
| description | `text` | nullable |
| acquisition_date | `date` |  |
| acquisition_cost | `decimal(15, 4)` |  |
| salvage_value | `decimal(15, 4)` | default(0) |
| useful_life_years | `decimal(8, 2)` | default(0) |
| accumulated_depreciation | `decimal(15, 4)` | default(0) |
| book_value | `decimal(15, 4)` | default(0) |
| depreciation_method | `string(50)` | nullable |
| status | `enum(['active', 'retired', 'transferred'])` | default('active') |
| retirement_date | `date` | nullable |
| retirement_amount | `decimal(15, 4)` | nullable |
| retirement_reason | `string` | nullable |
| journal_entry_id | `foreignId` | nullable, constrained('journal_entries'), nullOnDelete |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['fixed_asset_id', 'component_number'])`
- `$table->index(['organization_id', 'fixed_asset_id'])`
- `$table->index(['organization_id', 'status'])`

### asset_transactions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| fixed_asset_id | `unsignedBigInteger` |  |
| transaction_type | `enum(['acquisition', 'depreciation', 'impairment', 'revaluation', 'partial_disposal', 'full_disposal', 'write_off', 'transfer', ])` |  |
| transaction_date | `date` |  |
| amount | `decimal(15, 4)` |  |
| description | `string` |  |
| journal_entry_id | `unsignedBigInteger` | nullable |
| created_by | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'fixed_asset_id'])`
- `$table->index(['organization_id', 'transaction_type'])`

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete()`
- `$table->foreign('fixed_asset_id')->references('id')->on('fixed_assets')->restrictOnDelete()`
- `$table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete()`
- `$table->foreign('created_by')->references('id')->on('users')->nullOnDelete()`

### asset_transfers

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| transfer_number | `string(50)` |  |
| sending_organization_id | `unsignedBigInteger` |  |
| fixed_asset_id | `unsignedBigInteger` |  |
| receiving_organization_id | `unsignedBigInteger` |  |
| receiving_asset_id | `unsignedBigInteger` | nullable |
| transfer_date | `date` |  |
| transfer_type | `enum(['book_value', 'gross_value', 'negotiated_price'])` | default('book_value') |
| gross_value | `decimal(18, 4)` | comment('Original acquisition cost') |
| accumulated_depreciation | `decimal(18, 4)` | default(0) |
| net_book_value | `decimal(18, 4)` | comment('book value = gross - accum_dep') |
| transfer_price | `decimal(18, 4)` | nullable, comment('For negotiated_price type') |
| gain_loss_amount | `decimal(18, 4)` | default(0) |
| status | `enum(['pending', 'completed', 'cancelled'])` | default('pending') |
| cancellation_reason | `string(500)` | nullable |
| sending_journal_id | `unsignedBigInteger` | nullable |
| receiving_journal_id | `unsignedBigInteger` | nullable |
| notes | `text` | nullable |
| created_by | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['sending_organization_id', 'transfer_number'])`
- `$table->index(['sending_organization_id', 'status'])`
- `$table->index(['receiving_organization_id', 'status'])`

Foreign keys:

- `$table->foreign('sending_organization_id')->references('id')->on('organizations')->cascadeOnDelete()`
- `$table->foreign('fixed_asset_id')->references('id')->on('fixed_assets')->cascadeOnDelete()`
- `$table->foreign('receiving_organization_id')->references('id')->on('organizations')->cascadeOnDelete()`
- `$table->foreign('receiving_asset_id')->references('id')->on('fixed_assets')->nullOnDelete()`
- `$table->foreign('sending_journal_id')->references('id')->on('journal_entries')->nullOnDelete()`
- `$table->foreign('receiving_journal_id')->references('id')->on('journal_entries')->nullOnDelete()`
- `$table->foreign('created_by')->references('id')->on('users')->nullOnDelete()`

### currency_revaluations

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| revaluation_number | `string(30)` |  |
| revaluation_date | `date` |  |
| currency_code | `string(3)` | The foreign currency being revalued |
| old_rate | `decimal(15, 8)` | Previous exchange rate |
| new_rate | `decimal(15, 8)` | New exchange rate |
| base_currency | `string(3)` | Org's base currency |
| total_unrealized_gain | `decimal(18, 4)` | default(0) |
| total_unrealized_loss | `decimal(18, 4)` | default(0) |
| net_gain_loss | `decimal(18, 4)` | default(0) |
| gain_loss_account_id | `foreignId` | nullable, constrained('chart_of_accounts'), nullOnDelete |
| journal_entry_id | `foreignId` | nullable, constrained('journal_entries'), nullOnDelete |
| status | `string(20)` | default('draft') — draft, posted, reversed |
| notes | `text` | nullable |
| created_by | `foreignId` | constrained('users'), cascadeOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'revaluation_number'])`
- `$table->index(['organization_id', 'revaluation_date'])`
- `$table->index(['organization_id', 'currency_code'])`

### depreciation_run_lines

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| depreciation_run_id | `unsignedBigInteger` |  |
| fixed_asset_id | `unsignedBigInteger` |  |
| period_start | `date` |  |
| period_end | `date` |  |
| opening_book_value | `decimal(15, 4)` |  |
| depreciation_amount | `decimal(15, 4)` |  |
| closing_book_value | `decimal(15, 4)` |  |
| journal_entry_id | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['depreciation_run_id', 'fixed_asset_id'])`

Foreign keys:

- `$table->foreign('depreciation_run_id')->references('id')->on('depreciation_runs')->cascadeOnDelete()`
- `$table->foreign('fixed_asset_id')->references('id')->on('fixed_assets')->restrictOnDelete()`
- `$table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete()`

### elimination_entries

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| consolidation_period_id | `foreignId` | constrained('consolidation_periods'), cascadeOnDelete |
| entry_type | `enum(['intercompany_receivable', 'intercompany_payable', 'dividend', 'investment', 'other', ])` | default('intercompany_receivable') |
| description | `string` |  |
| debit_account_id | `foreignId` | constrained('chart_of_accounts') |
| credit_account_id | `foreignId` | constrained('chart_of_accounts') |
| amount | `decimal(15, 4)` |  |
| currency_code | `string(3)` | default('SAR') |
| journal_entry_id | `foreignId` | nullable, constrained('journal_entries'), nullOnDelete |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'consolidation_period_id'], 'elim_entries_org_period_idx')`

### forex_gain_loss_entries

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| entry_type | `string(20)` | realized, unrealized |
| transaction_type | `string(20)` | payment, receipt, transfer, revaluation |
| source_type | `string(100)` | PaymentReceived, PaymentMade, BankTransfer |
| source_id | `unsignedBigInteger` |  |
| foreign_currency | `string(3)` |  |
| base_currency | `string(3)` |  |
| foreign_amount | `decimal(18, 4)` |  |
| original_rate | `decimal(15, 8)` | Rate when invoice/bill was created |
| settlement_rate | `decimal(15, 8)` | Rate at payment time |
| gain_loss_amount | `decimal(18, 4)` | In base currency (positive = gain) |
| account_id | `foreignId` | nullable, constrained('chart_of_accounts'), nullOnDelete |
| journal_entry_id | `foreignId` | nullable, constrained('journal_entries'), nullOnDelete |
| transaction_date | `date` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'transaction_date'])`
- `$table->index(['source_type', 'source_id'])`
- `$table->index(['entry_type'])`

### installment_schedules

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| installment_plan_id | `unsignedBigInteger` |  |
| installment_number | `integer` | 1, 2, 3 … |
| amount | `decimal(18, 4)` |  |
| paid_amount | `decimal(18, 4)` | default(0) |
| due_date | `date` |  |
| paid_date | `date` | nullable |
| status | `enum(['pending', 'partial', 'paid', 'overdue', 'waived'])` | default('pending') |
| payment_id | `unsignedBigInteger` | nullable — FK to payments_received / payments_made |
| payment_type | `string(50)` | nullable — payment_received \| payment_made |
| journal_entry_id | `unsignedBigInteger` | nullable |
| notes | `string(500)` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['installment_plan_id', 'installment_number'], 'inst_sched_plan_num_uq')`
- `$table->index('due_date')`

Foreign keys:

- `$table->foreign('installment_plan_id')->references('id')->on('installment_plans')->cascadeOnDelete()`
- `$table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete()`

### lease_contracts

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| lease_number | `string(50)` |  |
| party_role | `enum(['lessee', 'lessor'])` | default('lessee') |
| asset_description | `string` |  |
| lessor_name | `string` | nullable |
| lessor_contact | `string(200)` | nullable |
| commencement_date | `date` |  |
| end_date | `date` |  |
| lease_term_months | `unsignedSmallInteger` |  |
| payment_amount | `decimal(18, 4)` |  |
| payment_frequency | `enum(['monthly', 'quarterly', 'semi_annual', 'annual'])` | default('monthly') |
| currency_code | `string(3)` | default('SAR') |
| discount_rate | `decimal(10, 6)` | comment('Annual discount rate, e.g. 0.05 for 5%') |
| classification | `enum(['finance', 'operating', 'short_term', 'low_value'])` | default('finance') |
| initial_rou_asset | `decimal(18, 4)` | default(0) |
| initial_lease_liability | `decimal(18, 4)` | default(0) |
| current_lease_liability | `decimal(18, 4)` | default(0) |
| rou_asset_account_id | `unsignedBigInteger` | nullable |
| accum_depreciation_account_id | `unsignedBigInteger` | nullable |
| lease_liability_account_id | `unsignedBigInteger` | nullable |
| interest_expense_account_id | `unsignedBigInteger` | nullable |
| depreciation_expense_account_id | `unsignedBigInteger` | nullable |
| status | `enum(['active', 'terminated', 'expired', 'modified'])` | default('active') |
| termination_date | `date` | nullable |
| notes | `text` | nullable |
| created_by | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'lease_number'])`
- `$table->index(['organization_id', 'status'])`
- `$table->index(['organization_id', 'commencement_date'])`

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete()`
- `$table->foreign('rou_asset_account_id')->references('id')->on('chart_of_accounts')->nullOnDelete()`
- `$table->foreign('accum_depreciation_account_id')->references('id')->on('chart_of_accounts')->nullOnDelete()`
- `$table->foreign('lease_liability_account_id')->references('id')->on('chart_of_accounts')->nullOnDelete()`
- `$table->foreign('interest_expense_account_id')->references('id')->on('chart_of_accounts')->nullOnDelete()`
- `$table->foreign('depreciation_expense_account_id')->references('id')->on('chart_of_accounts')->nullOnDelete()`
- `$table->foreign('created_by')->references('id')->on('users')->nullOnDelete()`

### lease_schedules

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| lease_contract_id | `unsignedBigInteger` |  |
| period_number | `unsignedSmallInteger` |  |
| payment_date | `date` |  |
| opening_balance | `decimal(18, 4)` |  |
| payment_amount | `decimal(18, 4)` |  |
| interest_portion | `decimal(18, 4)` |  |
| principal_portion | `decimal(18, 4)` |  |
| closing_balance | `decimal(18, 4)` |  |
| rou_depreciation | `decimal(18, 4)` | default(0) |
| is_posted | `boolean` | default(false) |
| journal_entry_id | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['lease_contract_id', 'period_number'])`
- `$table->index(['lease_contract_id', 'is_posted'])`

Foreign keys:

- `$table->foreign('lease_contract_id')->references('id')->on('lease_contracts')->cascadeOnDelete()`
- `$table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete()`

### liquidity_plans

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| plan_name | `string(100)` |  |
| plan_from | `date` |  |
| plan_to | `date` |  |
| granularity | `enum(['daily', 'weekly', 'monthly'])` | default('weekly') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id'], 'lp_org_idx')`

### liquidity_plan_lines

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| liquidity_plan_id | `foreignId` | constrained('liquidity_plans'), cascadeOnDelete |
| period_date | `date` |  |
| category | `string(100)` |  |
| flow_type | `enum(['inflow', 'outflow'])` | default('inflow') |
| planned_amount | `decimal(15, 4)` | default(0) |
| actual_amount | `decimal(15, 4)` | default(0) |
| currency_code | `string(3)` | default('SAR') |
| bank_account_id | `foreignId` | nullable, constrained('bank_accounts'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['liquidity_plan_id', 'period_date'], 'lpl_plan_date_idx')`

### material_ledger_records

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations') |
| product_id | `foreignId` | constrained('products'), name('mlr_product_fk') |
| warehouse_id | `foreignId` | nullable, constrained('warehouses'), name('mlr_warehouse_fk') |
| period | `unsignedTinyInteger` |  |
| fiscal_year | `unsignedSmallInteger` |  |
| status | `enum(['open', 'closed'])` | default('open') |
| opening_stock_qty | `decimal(18, 4)` | default(0) |
| opening_stock_value | `decimal(18, 4)` | default(0) |
| closing_stock_qty | `decimal(18, 4)` | default(0) |
| closing_stock_value | `decimal(18, 4)` | default(0) |
| cumulative_receipts_qty | `decimal(18, 4)` | default(0) |
| cumulative_receipts_value | `decimal(18, 4)` | default(0) |
| cumulative_issues_qty | `decimal(18, 4)` | default(0) |
| cumulative_issues_value | `decimal(18, 4)` | default(0) |
| standard_price | `decimal(18, 4)` | default(0) |
| actual_price | `decimal(18, 4)` | default(0) |
| price_difference | `decimal(18, 4)` | default(0) |
| price_unit | `unsignedInteger` | default(1) |
| currency_code | `char(3)` | default('SAR') |
| closed_at | `dateTime` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'product_id', 'warehouse_id', 'period', 'fiscal_year'], 'mlr_org_prod_wh_per_fy_unq')`
- `$table->index(['organization_id', 'period', 'fiscal_year'], 'mlr_org_period_fy_idx')`

### organization_currencies

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| currency_code | `string(3)` |  |
| is_base_currency | `boolean` | default(false) |
| is_active | `boolean` | default(true) |
| exchange_gain_account_id | `foreignId` | nullable, constrained('chart_of_accounts'), nullOnDelete |
| exchange_loss_account_id | `foreignId` | nullable, constrained('chart_of_accounts'), nullOnDelete |
| rounding_account_id | `foreignId` | nullable, constrained('chart_of_accounts'), nullOnDelete |
| rounding_precision | `decimal(8, 4)` | default(0.01) |
| rounding_method | `string(10)` | default('round') — round, ceil, floor |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'currency_code'])`

### overhead_keys

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| code | `string(30)` |  |
| name | `string` |  |
| overhead_type | `string(20)` | default('percentage') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'code'], 'ok_org_code_uq')`

Foreign keys:

- `$table->foreign('organization_id', 'ok_org_fk')->references('id')->on('organizations')->onDelete('cascade')`

### parked_documents

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string(36)` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| document_type | `string(30)` |  |
| reference | `string(50)` | nullable |
| document_date | `date` |  |
| posting_date | `date` |  |
| document_data | `json` |  |
| total_debit | `decimal(15, 4)` | default(0) |
| total_credit | `decimal(15, 4)` | default(0) |
| currency_code | `string(3)` | default('SAR') |
| parking_reason | `text` | nullable |
| parked_by | `foreignId` | constrained('users') |
| approved_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| status | `enum(['parked', 'pending_approval', 'posted', 'rejected'])` | default('parked') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'status'], 'pd_org_status_idx')`
- `$table->index(['organization_id', 'document_date'], 'pd_org_date_idx')`

### payment_advices

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| advice_number | `string(100)` | nullable — auto-generated |
| direction | `enum(['outgoing', 'incoming'])` | default('outgoing') |
| payment_type | `string(50)` | nullable — payment_received \| payment_made |
| payment_id | `unsignedBigInteger` | nullable |
| house_bank_id | `unsignedBigInteger` | nullable |
| house_bank_account_id | `unsignedBigInteger` | nullable |
| contact_id | `unsignedBigInteger` | nullable |
| currency_code | `string(3)` | default('SAR') |
| amount | `decimal(18, 4)` |  |
| payment_date | `date` |  |
| reference | `string(200)` | nullable — bank reference / UTR |
| narration | `string(500)` | nullable |
| status | `enum(['draft', 'sent', 'acknowledged', 'cancelled'])` | default('draft') |
| sent_at | `timestamp` | nullable |
| acknowledged_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'payment_date'])`
- `$table->index(['organization_id', 'contact_id'])`
- `$table->index(['organization_id', 'payment_type', 'payment_id'], 'pay_adv_org_pmt_idx')`

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete()`
- `$table->foreign('house_bank_id')->references('id')->on('house_banks')->nullOnDelete()`
- `$table->foreign('house_bank_account_id')->references('id')->on('house_bank_accounts')->nullOnDelete()`

### payment_runs

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string(36)` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| run_reference | `string(50)` |  |
| payment_direction | `enum(['outgoing', 'incoming'])` | default('outgoing') |
| payment_date | `date` |  |
| due_date_from | `date` | nullable |
| due_date_to | `date` | nullable |
| vendor_filter | `json` | nullable |
| payment_methods | `json` | nullable |
| minimum_payment | `decimal(15, 4)` | default(0) |
| currency_code | `string(3)` | default('SAR') |
| bank_account_id | `foreignId` | nullable, constrained('bank_accounts'), nullOnDelete |
| status | `enum(['draft', 'proposed', 'approved', 'posted', 'cancelled'])` | default('draft') |
| total_items | `integer` | default(0) |
| total_amount | `decimal(15, 4)` | default(0) |
| created_by | `foreignId` | constrained('users') |
| approved_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'run_reference'], 'pr_org_ref_unique')`
- `$table->index(['organization_id', 'status'], 'payment_runs_org_status_idx')`

### payment_files

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string` | unique |
| organization_id | `unsignedBigInteger` |  |
| payment_run_id | `unsignedBigInteger` | nullable |
| file_format | `string(20)` |  |
| file_name | `string` |  |
| file_content | `longText` |  |
| message_id | `string(50)` |  |
| creation_datetime | `dateTime` |  |
| number_of_transactions | `integer` | default(0) |
| total_amount | `decimal(18, 4)` |  |
| currency_code | `string(3)` |  |
| status | `string(20)` | default('generated') |
| submitted_at | `dateTime` | nullable |
| acknowledged_at | `dateTime` | nullable |
| error_message | `text` | nullable |
| created_by | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'status'], 'idx_pf_org_status')`
- `$table->index('payment_run_id', 'idx_pf_run')`

Foreign keys:

- `$table->foreign('organization_id', 'fk_pf_org')->references('id')->on('organizations')->onDelete('cascade')`
- `$table->foreign('payment_run_id', 'fk_pf_payment_run')->references('id')->on('payment_runs')->onDelete('set null')`
- `$table->foreign('created_by', 'fk_pf_created_by')->references('id')->on('users')->onDelete('set null')`

### payment_run_items

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| payment_run_id | `foreignId` | constrained('payment_runs'), cascadeOnDelete |
| document_type | `enum(['bill', 'purchase_order', 'invoice'])` |  |
| document_id | `unsignedBigInteger` |  |
| vendor_id | `unsignedBigInteger` | nullable |
| open_amount | `decimal(15, 4)` |  |
| payment_amount | `decimal(15, 4)` |  |
| discount_taken | `decimal(15, 4)` | default(0) |
| due_date | `date` | nullable |
| status | `enum(['proposed', 'included', 'excluded', 'paid'])` | default('proposed') |
| exclusion_reason | `string(255)` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['payment_run_id', 'status'], 'pri_run_status_idx')`
- `$table->index(['document_type', 'document_id'], 'pri_doc_idx')`

### payment_terms

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| code | `string(20)` |  |
| name | `string(100)` |  |
| net_days | `unsignedTinyInteger` | default(30), comment('Payment due in N days') |
| discount_days | `unsignedTinyInteger` | default(0), comment('Days within which discount applies') |
| discount_pct | `decimal(5, 2)` | default(0), comment('Cash discount percentage') |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'code'])`

### payment_tolerance_groups

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| code | `string(20)` | e.g. "DEFAULT", "KEY-ACC" |
| name | `string(200)` |  |
| description | `string(500)` | nullable |
| applies_to | `enum(['customer', 'supplier', 'both'])` | default('both') |
| is_active | `boolean` | default(true) |
| is_default | `boolean` | default(false) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'code'])`
- `$table->index('organization_id')`

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete()`

### payment_difference_posts

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| tolerance_group_id | `unsignedBigInteger` |  |
| payment_type | `string(50)` | payment_received \| payment_made |
| payment_id | `unsignedBigInteger` |  |
| contact_id | `unsignedBigInteger` | nullable |
| document_type | `string(50)` | nullable — invoice \| bill \| credit_note |
| document_id | `unsignedBigInteger` | nullable |
| currency_code | `string(3)` |  |
| invoice_amount | `decimal(18, 4)` |  |
| payment_amount | `decimal(18, 4)` |  |
| difference_amount | `decimal(18, 4)` | signed: negative = underpay |
| difference_type | `enum(['underpayment', 'overpayment'])` |  |
| resolution | `enum(['written_off', 'credited', 'auto_cleared'])` | default('written_off') |
| journal_entry_id | `unsignedBigInteger` | nullable |
| posting_date | `date` |  |
| notes | `string(500)` | nullable |
| created_by | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'payment_type', 'payment_id'], 'pay_diff_posts_org_type_id_idx')`
- `$table->index(['organization_id', 'posting_date'])`

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete()`
- `$table->foreign('tolerance_group_id')->references('id')->on('payment_tolerance_groups')`
- `$table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete()`

## 0080_accounting_4.php

### payment_tolerance_items

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| tolerance_group_id | `unsignedBigInteger` |  |
| currency_code | `string(3)` | ISO 4217 |
| underpay_abs | `decimal(18, 4)` | default(0) — absolute max underpayment |
| underpay_pct | `decimal(7, 4)` | default(0) — % of invoice amount |
| overpay_abs | `decimal(18, 4)` | default(0) |
| overpay_pct | `decimal(7, 4)` | default(0) |
| underpay_gl_account_id | `unsignedBigInteger` | nullable — expense |
| overpay_gl_account_id | `unsignedBigInteger` | nullable — income |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['tolerance_group_id', 'currency_code'])`

Foreign keys:

- `$table->foreign('tolerance_group_id')->references('id')->on('payment_tolerance_groups')->cascadeOnDelete()`
- `$table->foreign('underpay_gl_account_id')->references('id')->on('chart_of_accounts')->nullOnDelete()`
- `$table->foreign('overpay_gl_account_id')->references('id')->on('chart_of_accounts')->nullOnDelete()`

### period_lock_overrides

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `char(36)` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| period_id | `foreignId` | constrained('accounting_periods'), cascadeOnDelete |
| user_id | `foreignId` | constrained('users'), cascadeOnDelete |
| granted_by | `foreignId` | constrained('users'), cascadeOnDelete |
| reason | `text` |  |
| valid_until | `timestamp` | nullable |
| revoked_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'period_id'])`

### posting_validation_rules

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| rule_name | `string(100)` |  |
| rule_type | `enum(['validation', 'substitution'])` | default('validation') |
| trigger_event | `string(50)` | default('on_save') |
| conditions | `json` |  |
| actions | `json` |  |
| is_active | `boolean` | default(true) |
| priority | `integer` | default(10) |
| error_message | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'is_active', 'rule_type'], 'pvr_org_active_type_idx')`

### profitability_segments

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations') |
| segment_name | `string(100)` |  |
| customer_group_id | `foreignId` | nullable, constrained('customer_groups'), name('ps_cg_fk') |
| product_id | `foreignId` | nullable, constrained('products'), name('ps_prod_fk') |
| region | `string(100)` | nullable |
| sales_channel | `string(100)` | nullable |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id'], 'ps_org_idx')`

### assessment_cycles

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string(36)` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| name | `string(150)` |  |
| description | `text` | nullable |
| cycle_type | `enum(['assessment', 'distribution'])` | default('assessment') |
| fiscal_year | `unsignedSmallInteger` |  |
| period_from | `tinyInteger` | unsigned, default(1) — 1-12 |
| period_to | `tinyInteger` | unsigned, default(12) |
| status | `enum(['open', 'executed', 'reversed'])` | default('open') |
| executed_at | `timestamp` | nullable |
| executed_by | `foreignId` | nullable, constrained('users', 'id', 'co_asmt_cyc_exec_by_fk'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |
| copa_enabled | `boolean` | default(false) |
| copa_segment_id | `foreignId` | nullable, constrained('profitability_segments'), nullOnDelete |

Indexes:

- `$table->index(['organization_id', 'fiscal_year', 'status'], 'co_asmt_cyc_org_fy_status_idx')`

### special_ledgers

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string` | unique |
| organization_id | `unsignedBigInteger` |  |
| code | `string(50)` |  |
| name | `string` |  |
| description | `text` | nullable |
| accounting_principle | `string(50)` | default('ifrs') |
| is_leading | `boolean` | default(false) |
| is_active | `boolean` | default(true) |
| currency_code | `string(3)` | default('SAR') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'code'], 'uq_sl_org_code')`
- `$table->index(['organization_id', 'is_active'], 'idx_sl_org_active')`

Foreign keys:

- `$table->foreign('organization_id', 'fk_sl_org')->references('id')->on('organizations')->onDelete('cascade')`

### special_ledger_entries

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string` | unique |
| organization_id | `unsignedBigInteger` |  |
| special_ledger_id | `unsignedBigInteger` |  |
| journal_entry_id | `unsignedBigInteger` | nullable |
| account_id | `unsignedBigInteger` |  |
| posting_date | `date` |  |
| amount | `decimal(18, 4)` |  |
| currency_code | `string(3)` |  |
| exchange_rate | `decimal(18, 6)` | default(1) |
| amount_local | `decimal(18, 4)` |  |
| debit_credit | `char(1)` |  |
| period | `tinyInteger` |  |
| fiscal_year | `smallInteger` |  |
| cost_center_id | `unsignedBigInteger` | nullable |
| profit_center_id | `unsignedBigInteger` | nullable |
| reference_type | `string(50)` | nullable |
| reference_id | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['special_ledger_id', 'fiscal_year', 'period'], 'idx_sle_ledger_period')`
- `$table->index(['account_id', 'posting_date'], 'idx_sle_account_date')`
- `$table->index('organization_id', 'idx_sle_org')`

Foreign keys:

- `$table->foreign('special_ledger_id', 'fk_sle_ledger')->references('id')->on('special_ledgers')->onDelete('cascade')`
- `$table->foreign('journal_entry_id', 'fk_sle_je')->references('id')->on('journal_entries')->onDelete('set null')`
- `$table->foreign('account_id', 'fk_sle_account')->references('id')->on('chart_of_accounts')->onDelete('restrict')`

### special_ledger_mapping_rules

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string` | unique |
| organization_id | `unsignedBigInteger` |  |
| special_ledger_id | `unsignedBigInteger` |  |
| source_account_id | `unsignedBigInteger` | nullable |
| account_type | `string(50)` | nullable |
| target_account_id | `unsignedBigInteger` | nullable |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'is_active'], 'idx_slmr_org_active')`

Foreign keys:

- `$table->foreign('special_ledger_id', 'fk_slmr_ledger')->references('id')->on('special_ledgers')->onDelete('cascade')`
- `$table->foreign('source_account_id', 'fk_slmr_src_acct')->references('id')->on('chart_of_accounts')->onDelete('set null')`
- `$table->foreign('target_account_id', 'fk_slmr_tgt_acct')->references('id')->on('chart_of_accounts')->onDelete('set null')`

### statistical_key_figures

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations') |
| code | `string(20)` |  |
| name | `string(100)` |  |
| unit_of_measure | `string(20)` |  |
| skf_type | `enum(['fixed', 'total'])` | default('total') |
| is_active | `boolean` | default(true) |
| description | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'code'], 'skf_org_code_unq')`

### transfer_price_versions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| version_name | `string` |  |
| fiscal_year | `smallInteger` |  |
| status | `string(20)` | default('draft') |
| created_by | `unsignedBigInteger` | nullable |
| activated_at | `dateTime` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id', 'tpv_org_fk')->references('id')->on('organizations')->onDelete('cascade')`
- `$table->foreign('created_by', 'tpv_user_fk')->references('id')->on('users')->onDelete('set null')`

### treasury_investments

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string(36)` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| instrument_number | `string(30)` |  |
| instrument_type | `enum(['fixed_deposit', 'money_market', 'bond', 'treasury_bill', 'mutual_fund'])` | default('fixed_deposit') |
| counterparty | `string(150)` |  |
| principal_amount | `decimal(15, 4)` |  |
| interest_rate | `decimal(8, 4)` |  |
| investment_date | `date` |  |
| maturity_date | `date` |  |
| currency_code | `string(3)` | default('SAR') |
| bank_account_id | `foreignId` | nullable, constrained('bank_accounts'), nullOnDelete |
| accrued_interest | `decimal(15, 4)` | default(0) |
| maturity_value | `decimal(15, 4)` | nullable |
| status | `enum(['active', 'matured', 'pre_liquidated', 'rolled_over'])` | default('active') |
| gl_account_id | `foreignId` | nullable, constrained('chart_of_accounts'), nullOnDelete |
| created_by | `foreignId` | constrained('users') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'instrument_number'], 'ti_org_number_unique')`
- `$table->index(['organization_id', 'status', 'maturity_date'], 'ti_org_status_maturity_idx')`

### variance_analysis_runs

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations') |
| period | `unsignedTinyInteger` |  |
| fiscal_year | `unsignedSmallInteger` |  |
| run_type | `enum(['production_order', 'cost_center', 'project'])` | default('production_order') |
| status | `enum(['running', 'completed', 'failed'])` | default('running') |
| run_by | `foreignId` | nullable, constrained('users'), name('var_run_by_fk') |
| completed_at | `dateTime` | nullable |
| error_message | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'period', 'fiscal_year'], 'var_run_period_idx')`

### withholding_tax_codes

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| code | `string(20)` | e.g. WHT001, W1 |
| name | `string(200)` |  |
| description | `string(500)` | nullable |
| applicable_to | `enum(['supplier', 'customer', 'both'])` | default('supplier') |
| rate | `decimal(7, 4)` | 5.0000 = 5% |
| country_code | `string(3)` | nullable — ISO-3166 alpha-3 or alpha-2 |
| tax_type | `string(50)` | nullable — e.g. WHT, TCS, royalty |
| threshold_amount | `decimal(18, 4)` | nullable — minimum cumulative before WHT kicks in |
| ceiling_amount | `decimal(18, 4)` | nullable — max cumulative WHT per period |
| payable_account_id | `unsignedBigInteger` | nullable — Dr Expense / Cr WHT Payable |
| receivable_account_id | `unsignedBigInteger` | nullable — for customer-side (TCS) |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'code'])`
- `$table->index('organization_id')`

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete()`
- `$table->foreign('payable_account_id')->references('id')->on('chart_of_accounts')->nullOnDelete()`
- `$table->foreign('receivable_account_id')->references('id')->on('chart_of_accounts')->nullOnDelete()`

### withholding_tax_lines

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| wht_code_id | `unsignedBigInteger` |  |
| payment_type | `string(50)` | 'payment_received' \| 'payment_made' |
| payment_id | `unsignedBigInteger` |  |
| contact_id | `unsignedBigInteger` | nullable |
| gross_amount | `decimal(18, 4)` | taxable payment amount |
| wht_rate | `decimal(7, 4)` | rate applied (snapshot) |
| wht_amount | `decimal(18, 4)` | computed WHT |
| net_amount | `decimal(18, 4)` | gross - wht |
| currency_code | `string(3)` | default('SAR') |
| transaction_date | `date` |  |
| certificate_number | `string(100)` | nullable — WHT certificate issued |
| certificate_date | `date` | nullable |
| journal_entry_id | `unsignedBigInteger` | nullable |
| notes | `string(500)` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'payment_type', 'payment_id'], 'wht_lines_org_payment_idx')`
- `$table->index(['organization_id', 'contact_id'], 'wht_lines_org_contact_idx')`
- `$table->index(['organization_id', 'transaction_date'], 'wht_lines_org_date_idx')`

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete()`
- `$table->foreign('wht_code_id')->references('id')->on('withholding_tax_codes')->cascadeOnDelete()`
- `$table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete()`

### xbrl_taxonomies

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| name | `string` |  |
| version | `string(20)` |  |
| namespace | `string` | unique |
| schema_location | `string` | nullable |
| description | `text` | nullable |
| is_active | `boolean` | default(true) |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'is_active'])`

### xbrl_filings

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| fiscal_year_id | `foreignId` | constrained('fiscal_years'), cascadeOnDelete |
| taxonomy_id | `foreignId` | constrained('xbrl_taxonomies') |
| report_type | `enum(['annual', 'semi_annual', 'quarterly', 'interim'])` | default('annual') |
| period_start | `date` |  |
| period_end | `date` |  |
| status | `enum(['draft', 'validated', 'submitted', 'accepted', 'rejected'])` | default('draft') |
| xml_content | `longText` | nullable |
| validation_errors | `json` | nullable |
| external_reference | `string` | nullable |
| submitted_at | `timestamp` | nullable |
| accepted_at | `timestamp` | nullable |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'status'])`
- `$table->index(['organization_id', 'fiscal_year_id'])`

### xbrl_filing_elements

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| xbrl_filing_id | `foreignId` | constrained('xbrl_filings'), cascadeOnDelete |
| concept | `string` | e.g. ifrs-full:Equity |
| context_ref | `string` | e.g. duration_2023_2024 |
| unit_ref | `string` | nullable — e.g. SAR, ISO4217:USD |
| value | `string(1000)` | numeric or string value |
| decimals | `integer` | nullable |
| period_type | `enum(['instant', 'duration'])` | default('instant') |
| balance_type | `enum(['debit', 'credit'])` | nullable |
| sequence | `unsignedInteger` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('xbrl_filing_id')`
- `$table->index(['xbrl_filing_id', 'concept'])`

### zakat_assessments

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| fiscal_year_id | `foreignId` | nullable, constrained('fiscal_years'), nullOnDelete |
| assessment_year | `year` |  |
| hijri_year | `string(10)` | nullable — e.g. "1447" |
| total_assets | `decimal(18, 4)` | default(0) |
| total_liabilities | `decimal(18, 4)` | default(0) |
| non_zakatable_assets | `decimal(18, 4)` | default(0) — fixed assets, investments |
| zakat_base | `decimal(18, 4)` | default(0) — total_assets - total_liabilities - non_zakatable_assets |
| zakat_rate | `decimal(7, 4)` | default(2.5000) — 2.5% |
| zakat_due | `decimal(18, 4)` | default(0) — zakat_base × rate / 100 |
| saudi_ownership_pct | `decimal(7, 4)` | default(100.0000) — 100% for fully Saudi-owned |
| zakat_paid | `decimal(18, 4)` | default(0) |
| zakat_remaining | `decimal(18, 4)` | default(0) |
| status | `enum(['draft', 'submitted', 'assessed', 'paid'])` | default('draft') |
| gazt_reference | `string(100)` | nullable — GAZT / ZATCA filing reference |
| filing_due_date | `date` | nullable |
| filed_at | `date` | nullable |
| notes | `text` | nullable |
| prepared_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| approved_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'assessment_year'], 'zakat_org_year_unique')`
- `$table->index(['organization_id', 'status'])`

## 0090_admin_2.php

### announcement_reads

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| announcement_id | `foreignId` | constrained('system_announcements'), cascadeOnDelete |
| user_id | `foreignId` | constrained, cascadeOnDelete |
| is_dismissed | `boolean` | default(false) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['announcement_id', 'user_id'])`

### organization_admin_notes

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| admin_id | `foreignId` | constrained('platform_admins'), cascadeOnDelete |
| note | `text` |  |
| note_type | `string(30)` | default('general') — general, warning, support, billing |
| is_internal | `boolean` | default(true) — Visible only to admins |
| is_pinned | `boolean` | default(false) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'created_at'])`

### organization_status_history

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| admin_id | `foreignId` | nullable, constrained('platform_admins'), nullOnDelete |
| status_from | `string(30)` | nullable |
| status_to | `string(30)` |  |
| reason | `text` | nullable |
| metadata | `json` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'created_at'])`

### platform_admin_activities

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| admin_id | `foreignId` | constrained('platform_admins'), cascadeOnDelete |
| action | `string(100)` |  |
| entity_type | `string(100)` | nullable |
| entity_id | `unsignedBigInteger` | nullable |
| organization_id | `foreignId` | nullable, constrained, nullOnDelete |
| old_values | `json` | nullable |
| new_values | `json` | nullable |
| metadata | `json` | nullable |
| ip_address | `string(45)` | nullable |
| user_agent | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['admin_id', 'created_at'])`
- `$table->index(['entity_type', 'entity_id'])`
- `$table->index(['organization_id'])`

### support_tickets

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| ticket_number | `string(30)` | unique |
| organization_id | `foreignId` | nullable, constrained, nullOnDelete |
| user_id | `foreignId` | nullable, constrained, nullOnDelete |
| assigned_admin_id | `foreignId` | nullable, constrained('platform_admins'), nullOnDelete |
| subject | `string` |  |
| description | `text` |  |
| category | `string(50)` | technical, billing, feature_request, bug_report, general |
| priority | `string(20)` | default('medium') — low, medium, high, urgent |
| status | `string(30)` | default('open') — open, in_progress, waiting_response, resolved, closed |
| tags | `json` | nullable |
| source | `string(30)` | default('web') — web, email, api, phone |
| first_response_at | `timestamp` | nullable |
| resolved_at | `timestamp` | nullable |
| closed_at | `timestamp` | nullable |
| satisfaction_rating | `decimal(2, 1)` | nullable — 1-5 |
| satisfaction_feedback | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'status'])`
- `$table->index(['assigned_admin_id', 'status'])`
- `$table->index(['status', 'priority'])`

### support_ticket_messages

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| ticket_id | `foreignId` | constrained('support_tickets'), cascadeOnDelete |
| user_id | `foreignId` | nullable, constrained, nullOnDelete |
| admin_id | `foreignId` | nullable, constrained('platform_admins'), nullOnDelete |
| message | `text` |  |
| is_internal_note | `boolean` | default(false) — Only visible to admins |
| attachments | `json` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['ticket_id', 'created_at'])`

### dim_organization

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `unsignedBigInteger` |  |
| org_name | `string` |  |
| org_type | `string(50)` | nullable |
| country_code | `char(3)` |  |
| currency_code | `char(3)` |  |
| fiscal_year_start_month | `tinyInteger` | default(1) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id', 'dim_org_org_id_fk')->references('id')->on('organizations')->onDelete('cascade')`

### user_activity_logs

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| user_id | `foreignId` | nullable, constrained, nullOnDelete |
| organization_id | `foreignId` | nullable, constrained, nullOnDelete |
| method | `string(10)` | GET, POST, PUT, DELETE, PATCH |
| route_name | `string` | nullable — Named route (e.g. sales.invoices.store) |
| module | `string` | nullable — Resolved module (sales, hr, inventory...) |
| action | `string` | nullable — store, index, show, update, destroy |
| entity_type | `string` | nullable — Invoice, Employee, etc. |
| entity_id | `unsignedBigInteger` | nullable |
| response_status | `unsignedSmallInteger` | HTTP status code |
| duration_ms | `unsignedInteger` | nullable — Request duration |
| ip_address | `string(45)` | nullable |
| user_agent | `string` | nullable |
| device_type | `string` | nullable — mobile, tablet, desktop |
| browser | `string` | nullable |
| os | `string` | nullable |
| request_summary | `json` | nullable — non-sensitive subset of request params |
| created_at | `timestamp` | useCurrent |

Indexes:

- `$table->index(['user_id', 'created_at'])`
- `$table->index(['organization_id', 'module', 'created_at'])`
- `$table->index(['route_name', 'created_at'])`

### user_cluster_assignments

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| user_id | `foreignId` | constrained, cascadeOnDelete |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| cluster_name | `string` | high_value, inactive, power_user, etc. |
| algorithm | `string` | default('rule_based') — rule_based, kmeans (future) |
| confidence | `unsignedTinyInteger` | default(100) — 0-100 |
| dimensions | `json` | dimension values used for assignment |
| assigned_at | `timestamp` |  |
| expires_at | `timestamp` | nullable — re-evaluate after this date |
| created_at | `timestamp` | useCurrent |

Indexes:

- `$table->index(['user_id', 'cluster_name'])`
- `$table->index(['organization_id', 'cluster_name'])`

### user_feature_usage

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| user_id | `foreignId` | constrained, cascadeOnDelete |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| module | `string` | sales, hr, inventory, etc. |
| feature | `string` | invoices, employees, stock_transfer, etc. |
| usage_date | `date` |  |
| access_count | `unsignedInteger` | default(0) |
| create_count | `unsignedInteger` | default(0) |
| update_count | `unsignedInteger` | default(0) |
| delete_count | `unsignedInteger` | default(0) |
| total_duration_ms | `unsignedInteger` | default(0) |
| created_at | `timestamp` | useCurrent |
| updated_at | `timestamp` | useCurrent, useCurrentOnUpdate |

Indexes:

- `$table->unique(['user_id', 'module', 'feature', 'usage_date'])`
- `$table->index(['organization_id', 'module', 'usage_date'])`

### user_sessions_extended

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| user_id | `foreignId` | constrained, cascadeOnDelete |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| session_token_hash | `string(64)` | hashed JWT jti claim |
| ip_address | `string(45)` | nullable |
| user_agent | `string` | nullable |
| device_type | `string` | nullable |
| country_code | `string(2)` | nullable |
| city | `string` | nullable |
| started_at | `timestamp` |  |
| last_active_at | `timestamp` | nullable |
| ended_at | `timestamp` | nullable |
| duration_seconds | `unsignedInteger` | nullable — computed on logout |
| request_count | `unsignedInteger` | default(0) |
| modules_accessed | `json` | nullable — set of module names visited |
| end_reason | `string` | nullable — logout, timeout, token_blacklisted |
| created_at | `timestamp` | useCurrent |

Indexes:

- `$table->index(['user_id', 'started_at'])`
- `$table->index(['organization_id', 'started_at'])`

### automation_email_templates

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| name | `string` |  |
| subject | `string` |  |
| body_html | `text` |  |
| body_text | `text` | nullable |
| variables | `json` | nullable — Available variables |
| category | `string(50)` | nullable |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'name'])`

### automation_rules

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| name | `string` |  |
| description | `text` | nullable |
| trigger_type | `string(50)` | event, schedule, manual |
| trigger_event | `string` | nullable — invoice.created, payment.received, etc. |
| trigger_schedule | `string` | nullable — Cron expression for scheduled |
| entity_type | `string(50)` | invoice, customer, expense, etc. |
| conditions | `json` | Array of condition groups (AND/OR) |
| actions | `json` | Array of actions to execute |
| priority | `unsignedInteger` | default(0) |
| stop_on_match | `boolean` | default(false) — Stop processing other rules |
| is_active | `boolean` | default(true) |
| execution_count | `unsignedInteger` | default(0) |
| last_executed_at | `timestamp` | nullable |
| created_by | `foreignId` | constrained('users'), cascadeOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'entity_type', 'is_active'])`
- `$table->index(['organization_id', 'trigger_event', 'is_active'])`

### automation_rule_logs

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| rule_id | `foreignId` | constrained('automation_rules'), cascadeOnDelete |
| entity_type | `string` |  |
| entity_id | `unsignedBigInteger` |  |
| status | `string(20)` | success, failed, skipped |
| conditions_matched | `json` | nullable |
| actions_executed | `json` | nullable |
| error_message | `text` | nullable |
| execution_time_ms | `unsignedInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `morphs('entity')`
- `$table->index(['rule_id', 'created_at'])`

### automation_schedules

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| rule_id | `foreignId` | constrained('automation_rules'), cascadeOnDelete |
| scheduled_for | `timestamp` |  |
| executed_at | `timestamp` | nullable |
| status | `string(20)` | default('pending') — pending, running, completed, failed |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['scheduled_for', 'status'])`

## 0100_billing.php

### api_request_logs

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| user_id | `foreignId` | nullable, constrained, nullOnDelete |
| endpoint | `string(255)` |  |
| method | `string(10)` |  |
| response_status | `unsignedSmallInteger` |  |
| response_time_ms | `unsignedInteger` |  |
| ip_address | `string(45)` |  |
| api_version | `string(10)` | nullable |
| requested_at | `timestamp` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'requested_at'])`
- `$table->index(['requested_at'])`

### billing_credits

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| balance | `decimal(15, 2)` | default(0) |
| total_credited | `decimal(15, 2)` | default(0) |
| total_used | `decimal(15, 2)` | default(0) |
| currency_code | `string(3)` | default('USD') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id'])`

### billing_payment_methods

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| type | `string(30)` | card, bank_transfer, wallet |
| provider | `string(30)` | stripe, paypal, razorpay |
| provider_payment_method_id | `string` | nullable — External ID |
| card_brand | `string(20)` | nullable |
| card_last_four | `string(4)` | nullable |
| card_exp_month | `unsignedSmallInteger` | nullable |
| card_exp_year | `unsignedSmallInteger` | nullable |
| bank_name | `string` | nullable |
| bank_account_last_four | `string(4)` | nullable |
| is_default | `boolean` | default(false) |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'is_default'])`

### organization_subscriptions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| plan_id | `foreignId` | constrained('subscription_plans'), cascadeOnDelete |
| status | `string(30)` | default('active') — trial, active, past_due, cancelled, expired, suspended |
| starts_at | `date` |  |
| ends_at | `date` | nullable |
| trial_ends_at | `date` | nullable |
| cancelled_at | `date` | nullable |
| cancellation_reason | `text` | nullable |
| base_price | `decimal(15, 2)` |  |
| discount_amount | `decimal(15, 2)` | default(0) |
| discount_percent | `decimal(5, 2)` | default(0) |
| discount_code | `string` | nullable |
| max_users | `unsignedInteger` | nullable |
| max_branches | `unsignedInteger` | nullable |
| storage_limit_mb | `unsignedBigInteger` | nullable |
| max_invoices_per_month | `unsignedInteger` | nullable |
| enabled_modules | `json` | nullable |
| enabled_features | `json` | nullable |
| auto_renew | `boolean` | default(true) |
| payment_method_id | `string` | nullable |
| next_billing_date | `date` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'status'])`
- `$table->index(['status', 'ends_at'])`

### billing_invoices

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| invoice_number | `string(30)` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| subscription_id | `foreignId` | nullable, constrained('organization_subscriptions'), nullOnDelete |
| billing_period_start | `date` |  |
| billing_period_end | `date` |  |
| invoice_date | `date` |  |
| due_date | `date` |  |
| currency_code | `string(3)` | default('USD') |
| subtotal | `decimal(15, 2)` |  |
| discount_amount | `decimal(15, 2)` | default(0) |
| tax_amount | `decimal(15, 2)` | default(0) |
| total | `decimal(15, 2)` |  |
| amount_paid | `decimal(15, 2)` | default(0) |
| amount_due | `decimal(15, 2)` |  |
| status | `string(20)` | default('draft') — draft, sent, paid, partial, overdue, cancelled, refunded |
| sent_at | `timestamp` | nullable |
| paid_at | `timestamp` | nullable |
| pdf_path | `string` | nullable |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'status'])`
- `$table->index(['status', 'due_date'])`

### billing_invoice_items

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| invoice_id | `foreignId` | constrained('billing_invoices'), cascadeOnDelete |
| item_type | `string(30)` | subscription, addon, overage, credit, adjustment |
| description | `string` |  |
| quantity | `decimal(15, 4)` | default(1) |
| unit_label | `string` | nullable |
| unit_price | `decimal(15, 4)` |  |
| discount_amount | `decimal(15, 2)` | default(0) |
| tax_rate | `decimal(5, 2)` | default(0) |
| tax_amount | `decimal(15, 2)` | default(0) |
| total | `decimal(15, 2)` |  |
| plan_id | `foreignId` | nullable, constrained('subscription_plans'), nullOnDelete |
| addon_id | `foreignId` | nullable, constrained('subscription_addons'), nullOnDelete |
| metric_type | `string` | nullable — For usage-based items |
| line_order | `unsignedSmallInteger` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['invoice_id', 'line_order'])`

### billing_payments

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| transaction_id | `string(100)` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| invoice_id | `foreignId` | nullable, constrained('billing_invoices'), nullOnDelete |
| payment_method_id | `foreignId` | nullable, constrained('billing_payment_methods'), nullOnDelete |
| amount | `decimal(15, 2)` |  |
| currency_code | `string(3)` |  |
| payment_type | `string(30)` | subscription, addon, overage, credit_purchase |
| provider | `string(30)` | stripe, paypal, manual |
| provider_transaction_id | `string` | nullable |
| status | `string(20)` | default('pending') — pending, processing, completed, failed, refunded |
| processed_at | `timestamp` | nullable |
| failure_reason | `text` | nullable |
| failure_code | `string` | nullable |
| is_refunded | `boolean` | default(false) |
| refunded_amount | `decimal(15, 2)` | default(0) |
| refunded_at | `timestamp` | nullable |
| refund_reason | `text` | nullable |
| provider_response | `json` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'status'])`
- `$table->index(['provider_transaction_id'])`

### billing_credit_transactions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| transaction_type | `string(30)` | purchase, bonus, referral, applied, expired, refund |
| amount | `decimal(15, 2)` |  |
| balance_before | `decimal(15, 2)` |  |
| balance_after | `decimal(15, 2)` |  |
| description | `text` | nullable |
| invoice_id | `foreignId` | nullable, constrained('billing_invoices'), nullOnDelete |
| payment_id | `foreignId` | nullable, constrained('billing_payments'), nullOnDelete |
| expires_at | `date` | nullable |
| created_by | `foreignId` | nullable, constrained('platform_admins'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'created_at'])`

### discount_code_usages

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| discount_code_id | `foreignId` | constrained('discount_codes'), cascadeOnDelete |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| invoice_id | `foreignId` | nullable, constrained('billing_invoices'), nullOnDelete |
| discount_amount | `decimal(15, 2)` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['discount_code_id', 'organization_id'])`

### subscription_addon_purchases

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| subscription_id | `foreignId` | constrained('organization_subscriptions'), cascadeOnDelete |
| addon_id | `foreignId` | constrained('subscription_addons'), cascadeOnDelete |
| quantity | `unsignedInteger` | default(1) |
| unit_price | `decimal(15, 2)` |  |
| total_price | `decimal(15, 2)` |  |
| starts_at | `date` |  |
| ends_at | `date` | nullable |
| status | `string(20)` | default('active') — active, cancelled, expired |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['subscription_id', 'status'])`

### usage_aggregates

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| metric_type | `string(50)` |  |
| period_type | `string(10)` | daily, monthly |
| period | `string(10)` | 2024-01-15 or 2024-01 |
| total_quantity | `unsignedBigInteger` |  |
| peak_quantity | `unsignedBigInteger` | nullable |
| average_quantity | `decimal(15, 2)` | nullable |
| breakdown | `json` | nullable — Detailed breakdown by feature/endpoint |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'metric_type', 'period_type', 'period'], 'usage_agg_org_metric_period_unique')`
- `$table->index(['organization_id', 'period_type', 'period'])`

### usage_alerts

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| metric_type | `string(50)` |  |
| threshold_percent | `unsignedTinyInteger` | 80, 90, 100 |
| threshold_value | `unsignedBigInteger` |  |
| current_value | `unsignedBigInteger` |  |
| status | `string(20)` | default('triggered') — triggered, notified, resolved |
| notified_at | `timestamp` | nullable |
| resolved_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'status'])`

### usage_metrics

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| metric_type | `string(50)` | api_calls, storage_mb, invoices_created, users_active, sms_sent, emails_sent |
| quantity | `unsignedBigInteger` |  |
| metric_date | `date` |  |
| billing_period | `string(7)` | nullable — 2024-01 format |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'metric_type', 'metric_date'])`
- `$table->index(['organization_id', 'billing_period'])`

### usage_snapshots

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| users_count | `unsignedInteger` | default(0) |
| branches_count | `unsignedInteger` | default(0) |
| storage_used_mb | `unsignedBigInteger` | default(0) |
| invoices_this_month | `unsignedInteger` | default(0) |
| products_count | `unsignedInteger` | default(0) |
| customers_count | `unsignedInteger` | default(0) |
| employees_count | `unsignedInteger` | default(0) |
| api_calls_this_month | `unsignedBigInteger` | default(0) |
| snapshot_at | `timestamp` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id'])`

### budgets

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| fiscal_year_id | `foreignId` | nullable, constrained('fiscal_years'), nullOnDelete |
| name | `string` |  |
| budget_type | `enum(['annual', 'quarterly', 'project', 'department'])` | default('annual') |
| status | `enum(['draft', 'submitted', 'approved', 'active', 'closed', 'cancelled'])` | default('draft') |
| period_start | `date` |  |
| period_end | `date` |  |
| currency_code | `string(3)` | default('SAR') |
| total_amount | `decimal(15, 2)` | default(0) |
| approved_amount | `decimal(15, 2)` | nullable |
| description | `text` | nullable |
| approved_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| approved_at | `timestamp` | nullable |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| deleted_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'status'])`
- `$table->index(['organization_id', 'fiscal_year_id'])`

### budget_revisions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| budget_id | `foreignId` | constrained('budgets'), cascadeOnDelete |
| revision_number | `smallInteger` | unsigned |
| reason | `text` |  |
| previous_total | `decimal(15, 2)` |  |
| new_total | `decimal(15, 2)` |  |
| status | `enum(['draft', 'approved'])` | default('draft') |
| approved_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| approved_at | `timestamp` | nullable |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['budget_id', 'revision_number'])`

## 0110_calendar.php

### calendars

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| user_id | `foreignId` | nullable, constrained, nullOnDelete — Personal calendar |
| name | `string` |  |
| color | `string(7)` | default('#3B82F6') |
| description | `text` | nullable |
| type | `string(20)` | default('personal') — personal, team, organization, resource |
| is_default | `boolean` | default(false) |
| is_visible | `boolean` | default(true) |
| timezone | `string(50)` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'user_id'])`

### calendar_events

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| calendar_id | `foreignId` | constrained, cascadeOnDelete |
| created_by | `foreignId` | constrained('users'), cascadeOnDelete |
| title | `string` |  |
| description | `text` | nullable |
| location | `string` | nullable |
| event_type | `string(30)` | default('event') — event, meeting, task, reminder, holiday |
| start_at | `dateTime` |  |
| end_at | `dateTime` | nullable |
| is_all_day | `boolean` | default(false) |
| timezone | `string(50)` | nullable |
| status | `string(20)` | default('confirmed') — tentative, confirmed, cancelled |
| visibility | `string(20)` | default('default') — default, public, private |
| color | `string(7)` | nullable |
| related_type | `string` | nullable |
| related_id | `unsignedBigInteger` | nullable |
| attendees | `json` | nullable — Quick attendee list (JSON) |
| is_recurring | `boolean` | default(false) |
| recurring_event_id | `foreignId` | nullable — Parent recurring event |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `nullableMorphs('related')`
- `$table->index(['calendar_id', 'start_at', 'end_at'])`
- `$table->index(['organization_id', 'start_at'])`

Foreign keys:

- `$table->foreign('recurring_event_id', 'cal_event_recurring_parent_fk')->references('id')->on('calendar_events')->nullOnDelete()`

### calendar_event_attendees

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| event_id | `foreignId` | constrained('calendar_events'), cascadeOnDelete |
| user_id | `foreignId` | nullable, constrained, nullOnDelete |
| email | `string` | nullable |
| name | `string` | nullable |
| role | `string(20)` | default('attendee') — organizer, attendee, optional |
| status | `string(20)` | default('pending') — pending, accepted, declined, tentative |
| comment | `text` | nullable |
| responded_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['event_id', 'user_id'])`
- `$table->index(['user_id', 'status'])`

### calendar_event_reminders

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| event_id | `foreignId` | constrained('calendar_events'), cascadeOnDelete |
| method | `string(20)` | default('notification') — notification, email, sms |
| reminder_minutes | `unsignedInteger` | default(15) |
| minutes_before | `unsignedInteger` | nullable — legacy alias |
| is_sent | `boolean` | default(false) |
| sent_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

### calendar_recurring_rules

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| event_id | `foreignId` | constrained('calendar_events'), cascadeOnDelete |
| frequency | `string(20)` | daily, weekly, monthly, yearly |
| interval | `unsignedInteger` | default(1) |
| days_of_week | `json` | nullable — [MO, TU, WE] for weekly |
| days_of_month | `json` | nullable — [1, 15] for monthly |
| months_of_year | `json` | nullable — [1, 6, 12] for yearly |
| ends_at | `date` | nullable |
| max_occurrences | `unsignedInteger` | nullable |
| exception_dates | `json` | nullable — Dates to skip |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

### reminders

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| user_id | `foreignId` | constrained, cascadeOnDelete |
| title | `string` |  |
| description | `text` | nullable |
| remind_at | `dateTime` |  |
| frequency | `string(20)` | nullable — once, daily, weekly, monthly |
| remindable_type | `string` |  |
| remindable_id | `unsignedBigInteger` |  |
| is_sent | `boolean` | default(false) |
| sent_at | `timestamp` | nullable |
| is_dismissed | `boolean` | default(false) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `morphs('remindable')`
- `$table->index(['user_id', 'remind_at', 'is_sent'])`

### user_segments

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| name | `string` |  |
| description | `text` | nullable |
| conditions | `json` |  |
| color | `string(7)` | default('#6366f1') |
| is_dynamic | `boolean` | default(true) |
| member_count | `unsignedInteger` | default(0) |
| last_evaluated_at | `timestamp` | nullable |
| created_by | `foreignId` | constrained('users'), cascadeOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

### campaigns

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| name | `string` |  |
| description | `text` | nullable |
| trigger_event | `string` | nullable |
| conditions | `json` | nullable |
| target_segment_id | `foreignId` | nullable, references('id'), on('user_segments'), nullOnDelete |
| actions | `json` |  |
| status | `string` | default('draft') |
| schedule_type | `string` | default('immediate') |
| delay_minutes | `unsignedInteger` | nullable |
| scheduled_at | `timestamp` | nullable |
| start_date | `date` | nullable |
| end_date | `date` | nullable |
| max_sends_per_user | `unsignedInteger` | default(1) |
| created_by | `foreignId` | constrained('users'), cascadeOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

### campaign_sends

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| campaign_id | `foreignId` | constrained, cascadeOnDelete |
| user_id | `foreignId` | constrained, cascadeOnDelete |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| status | `string` | default('pending') |
| channel | `string(20)` | nullable |
| sent_at | `timestamp` | nullable |
| error_message | `text` | nullable |
| metadata | `json` | nullable |
| created_at | `timestamp` | useCurrent |

Indexes:

- `$table->index(['campaign_id', 'user_id'])`
- `$table->index(['organization_id', 'status'])`

## 0120_compliance.php

### bahrain_vat_returns

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| period_type | `enum(['quarterly', 'monthly'])` | default('quarterly') |
| period_quarter | `tinyInteger` | nullable — 1-4 for quarterly |
| period_month | `tinyInteger` | nullable — 1-12 for monthly |
| period_year | `year` |  |
| period_start | `date` |  |
| period_end | `date` |  |
| standard_rated_supplies | `decimal(18, 4)` | default(0) — Box 1: taxable sales (BHD) |
| zero_rated_supplies | `decimal(18, 4)` | default(0) — Box 2 |
| exempt_supplies | `decimal(18, 4)` | default(0) — Box 3 |
| output_vat | `decimal(18, 4)` | default(0) — Box 4: 10% × Box 1 |
| standard_rated_purchases | `decimal(18, 4)` | default(0) — Box 5 |
| capital_goods_input_tax | `decimal(18, 4)` | default(0) — Box 6 |
| total_input_vat | `decimal(18, 4)` | default(0) — Box 7 |
| net_vat_payable | `decimal(18, 4)` | default(0) — Box 8 (negative = refund) |
| vat_rate | `decimal(7, 4)` | default(10.0) — 10% (effective Jan 2022) |
| status | `enum(['draft', 'submitted', 'accepted', 'paid'])` | default('draft') |
| nbr_reference | `string(100)` | nullable |
| filing_due_date | `date` | nullable |
| filed_at | `date` | nullable |
| notes | `text` | nullable |
| prepared_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'period_year', 'period_quarter', 'period_month'], 'bh_vat_period_unique')`
- `$table->index(['organization_id', 'status'])`

### dps_sanction_lists

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| list_name | `string(100)` |  |
| list_authority | `string(50)` | OFAC\|EU\|UN\|HMT\|local\|other |
| list_type | `string(20)` | denied_party\|embargo\|debarred |
| last_updated_at | `dateTime` | nullable |
| entry_count | `integer` | default(0) |
| is_active | `boolean` | default(true) |
| auto_sync | `boolean` | default(false) |
| sync_url | `string(255)` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

### dps_list_entries

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| dps_sanction_list_id | `foreignId` | constrained('dps_sanction_lists'), cascadeOnDelete |
| entry_type | `string(20)` | person\|entity\|vessel\|aircraft |
| name | `string(200)` |  |
| aliases | `json` | nullable |
| country_code | `string(3)` | nullable |
| address | `string(300)` | nullable |
| id_number | `string(100)` | nullable |
| program | `string(100)` | nullable |
| remarks | `text` | nullable |
| effective_date | `date` | nullable |
| expiry_date | `date` | nullable |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['dps_sanction_list_id', 'is_active'], 'dps_entries_list_active_idx')`
- `$table->index('name', 'dps_entries_name_idx')`
- `$table->index('country_code', 'dps_entries_country_idx')`

### dps_screening_runs

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| screened_entity_type | `string(20)` | contact\|vendor\|customer |
| screened_entity_id | `unsignedBigInteger` |  |
| screening_date | `dateTime` |  |
| match_threshold | `decimal(5, 2)` | default(80) |
| status | `string(20)` | default('clean') — clean\|potential_match\|confirmed_match\|cleared |
| cleared_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| cleared_at | `dateTime` | nullable |
| clearance_notes | `text` | nullable |
| triggered_by | `string(50)` | manual\|auto_transaction\|batch |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['screened_entity_type', 'screened_entity_id'], 'dps_runs_entity_idx')`
- `$table->index(['status', 'screening_date'], 'dps_runs_status_date_idx')`

### dps_screening_results

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| dps_screening_run_id | `foreignId` | constrained('dps_screening_runs'), cascadeOnDelete |
| dps_list_entry_id | `foreignId` | constrained('dps_list_entries'), cascadeOnDelete |
| match_score | `decimal(5, 2)` |  |
| matched_field | `string(30)` | name\|alias\|id_number\|address |
| is_false_positive | `boolean` | default(false) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('dps_screening_run_id', 'dps_results_run_idx')`

### uae_cit_assessments

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| fiscal_year_id | `foreignId` | nullable, constrained('fiscal_years'), nullOnDelete |
| tax_year | `year` |  |
| accounting_income | `decimal(18, 4)` | default(0) — net profit per books |
| add_backs | `decimal(18, 4)` | default(0) — non-deductible expenses |
| deductions | `decimal(18, 4)` | default(0) — exempt income / allowances |
| taxable_income | `decimal(18, 4)` | default(0) — accounting_income + add_backs - deductions |
| zero_rate_threshold | `decimal(18, 4)` | default(375000.0) — AED 375,000 |
| small_business_threshold | `decimal(18, 4)` | default(3000000.0) — AED 3,000,000 |
| cit_rate | `decimal(7, 4)` | default(9.0000) — 9% |
| small_business_relief | `boolean` | default(false) — elected SBR |
| cit_due | `decimal(18, 4)` | default(0) — payable tax |
| cit_paid | `decimal(18, 4)` | default(0) |
| cit_remaining | `decimal(18, 4)` | default(0) |
| status | `enum(['draft', 'submitted', 'assessed', 'paid'])` | default('draft') |
| emara_tax_reference | `string(100)` | nullable |
| filing_due_date | `date` | nullable |
| filed_at | `date` | nullable |
| notes | `text` | nullable |
| prepared_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'tax_year'], 'cit_org_year_unique')`
- `$table->index(['organization_id', 'status'])`

## 0130_crm.php

### crm_activities

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| activity_type | `enum(['call', 'email', 'meeting', 'task', 'note', 'follow_up', ])` |  |
| subject | `string(200)` |  |
| description | `text` | nullable |
| related_type | `string(50)` | nullable — lead, opportunity, contact |
| related_id | `unsignedBigInteger` | nullable |
| start_datetime | `datetime` | nullable |
| end_datetime | `datetime` | nullable |
| duration_minutes | `unsignedSmallInteger` | nullable |
| is_all_day | `boolean` | default(false) |
| status | `enum(['planned', 'in_progress', 'completed', 'cancelled', ])` | default('planned') |
| priority | `enum(['low', 'medium', 'high'])` | default('medium') |
| completed_at | `datetime` | nullable |
| call_direction | `enum(['inbound', 'outbound'])` | nullable |
| call_result | `enum(['connected', 'no_answer', 'busy', 'voicemail', 'wrong_number'])` | nullable |
| location | `string(200)` | nullable |
| meeting_link | `string(500)` | nullable |
| assigned_to | `foreignId` | nullable, constrained('users'), nullOnDelete |
| attendees | `json` | nullable — Array of user IDs or contact IDs |
| reminder_datetime | `datetime` | nullable |
| reminder_sent | `boolean` | default(false) |
| outcome | `text` | nullable |
| notes | `text` | nullable |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'activity_type'])`
- `$table->index(['organization_id', 'status'])`
- `$table->index(['related_type', 'related_id'])`
- `$table->index(['organization_id', 'assigned_to', 'status'])`
- `$table->index(['organization_id', 'start_datetime'])`

### lead_sources

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| name | `string(100)` |  |
| code | `string(20)` | nullable |
| description | `text` | nullable |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'code'])`

### pipeline_stages

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| name | `string(100)` |  |
| code | `string(20)` | nullable |
| description | `text` | nullable |
| probability | `unsignedSmallInteger` | default(0) — 0-100% |
| sort_order | `unsignedSmallInteger` | default(0) |
| color | `string(7)` | nullable |
| is_won | `boolean` | default(false) |
| is_lost | `boolean` | default(false) |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'code'])`

### sla_policies

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| name | `string` |  |
| description | `text` | nullable |
| priority | `string` | low, medium, high, critical |
| first_response_hours | `unsignedInteger` |  |
| resolution_hours | `unsignedInteger` |  |
| business_hours_only | `boolean` | default(true) |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'priority', 'is_active'])`

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade')`

### territories

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| parent_id | `foreignId` | nullable, constrained('territories'), nullOnDelete |
| name | `string` |  |
| code | `string` |  |
| description | `text` | nullable |
| territory_type | `enum(['global', 'region', 'country', 'state', 'city', 'postal_zone', 'custom', ])` | default('custom') |
| country_code | `string(3)` | nullable |
| state_code | `string(10)` | nullable |
| postal_codes | `json` | nullable, comment('Array of postal code patterns') |
| status | `enum(['active', 'inactive'])` | default('active') |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| deleted_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'code'])`

### territory_routing_rules

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| territory_id | `foreignId` | constrained('territories'), cascadeOnDelete |
| entity_type | `enum(['lead', 'opportunity', 'contact'])` | default('lead') |
| match_field | `enum(['country', 'state', 'postal_code', 'city', 'custom'])` | default('country') |
| match_value | `string` |  |
| priority | `tinyInteger` | default(10) |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'entity_type'])`

## 0140_document.php

### document_folders

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| parent_id | `foreignId` | nullable, constrained('document_folders'), nullOnDelete |
| name | `string` |  |
| color | `string(7)` | nullable — Hex color |
| icon | `string(50)` | nullable |
| description | `text` | nullable |
| is_system | `boolean` | default(false) — System folders can't be deleted |
| access_level | `string(20)` | default('organization') — organization, branch, private |
| created_by | `foreignId` | constrained('users'), cascadeOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'parent_id'])`

### documents

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| folder_id | `foreignId` | nullable, constrained('document_folders'), nullOnDelete |
| name | `string` |  |
| file_name | `string` |  |
| file_path | `string` |  |
| mime_type | `string(100)` |  |
| file_size | `unsignedBigInteger` |  |
| extension | `string(10)` |  |
| description | `text` | nullable |
| tags | `json` | nullable |
| document_type | `string(50)` | nullable — contract, invoice, receipt, id_proof, etc. |
| document_date | `date` | nullable |
| expiry_date | `date` | nullable |
| is_expiry_notified | `boolean` | default(false) |
| documentable_type | `string` | nullable |
| documentable_id | `unsignedBigInteger` | nullable |
| access_level | `string(20)` | default('organization') — organization, branch, private |
| is_archived | `boolean` | default(false) |
| uploaded_by | `foreignId` | constrained('users'), cascadeOnDelete |
| download_count | `unsignedInteger` | default(0) |
| last_accessed_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `nullableMorphs('documentable')`
- `$table->index(['organization_id', 'folder_id'])`
- `$table->index(['organization_id', 'document_type'])`
- `$table->index(['organization_id', 'expiry_date'])`
- `$table->fullText(['name', 'description'])`

### digital_signatures

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| document_id | `foreignId` | constrained, cascadeOnDelete |
| signer_id | `foreignId` | nullable, constrained('users'), nullOnDelete |
| signer_email | `string` |  |
| signer_name | `string` |  |
| status | `string(20)` | default('pending') — pending, signed, declined, expired |
| signature_data | `text` | nullable — Base64 signature image |
| ip_address | `string(45)` | nullable |
| user_agent | `string` | nullable |
| signed_at | `timestamp` | nullable |
| expires_at | `timestamp` | nullable |
| verification_code | `string(32)` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['document_id', 'status'])`
- `$table->index(['signer_email', 'status'])`

### document_activities

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| document_id | `foreignId` | constrained, cascadeOnDelete |
| user_id | `foreignId` | constrained, cascadeOnDelete |
| action | `string(30)` | viewed, downloaded, uploaded, edited, shared, deleted |
| metadata | `json` | nullable |
| ip_address | `string(45)` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['document_id', 'created_at'])`

### document_permissions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| document_id | `foreignId` | nullable, constrained, cascadeOnDelete |
| folder_id | `foreignId` | nullable, constrained('document_folders'), cascadeOnDelete |
| permissible_type | `string` |  |
| permissible_id | `unsignedBigInteger` |  |
| permission | `string(20)` | view, download, edit, delete, manage |
| granted_by | `foreignId` | constrained('users'), cascadeOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `morphs('permissible')`
- `$table->index(['document_id', 'permissible_type', 'permissible_id'], 'doc_perm_doc_permissible_idx')`
- `$table->index(['folder_id', 'permissible_type', 'permissible_id'], 'doc_perm_folder_permissible_idx')`

### document_shares

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| document_id | `foreignId` | constrained, cascadeOnDelete |
| shared_by | `foreignId` | constrained('users'), cascadeOnDelete |
| share_type | `string(20)` | default('link') — link, email |
| recipient_email | `string` | nullable |
| access_code | `string(32)` | nullable — Optional password |
| allow_download | `boolean` | default(true) |
| max_downloads | `unsignedInteger` | nullable |
| download_count | `unsignedInteger` | default(0) |
| expires_at | `timestamp` | nullable |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['uuid', 'is_active'])`

### document_versions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| document_id | `foreignId` | constrained, cascadeOnDelete |
| version_number | `unsignedInteger` |  |
| file_path | `string` |  |
| mime_type | `string(100)` | nullable |
| file_size | `unsignedBigInteger` |  |
| change_summary | `string` | nullable |
| change_notes | `text` | nullable |
| uploaded_by | `foreignId` | constrained('users'), cascadeOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['document_id', 'version_number'])`

### payment_gateways

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| name | `string` |  |
| provider | `string(30)` | stripe, paypal, tap, moyasar, hyperpay, mada |
| credentials | `text` | nullable |
| settings | `json` | nullable |
| mode | `string(10)` | default('test') — test, live |
| is_active | `boolean` | default(true) |
| is_default | `boolean` | default(false) |
| supported_currencies | `json` | nullable |
| supported_methods | `json` | nullable — card, mada, apple_pay, etc. |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'is_active'])`

### expense_categories

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| parent_id | `foreignId` | nullable, constrained('expense_categories'), nullOnDelete |
| name | `string` |  |
| code | `string(20)` | nullable |
| icon | `string(50)` | nullable |
| color | `string(7)` | nullable |
| description | `text` | nullable |
| default_account_id | `foreignId` | nullable, constrained('chart_of_accounts'), nullOnDelete |
| is_active | `boolean` | default(true) |
| requires_receipt | `boolean` | default(false) |
| budget_limit | `decimal(15, 2)` | nullable — Monthly budget |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'name', 'parent_id'])`

### petty_cash_funds

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string(36)` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| branch_id | `foreignId` | nullable, constrained('branches'), nullOnDelete |
| name | `string(100)` |  |
| custodian_id | `foreignId` | constrained('users') |
| account_id | `foreignId` | constrained('chart_of_accounts') |
| opening_balance | `decimal(15, 4)` | default(0) |
| current_balance | `decimal(15, 4)` | default(0) |
| max_transaction_limit | `decimal(15, 4)` | default(0) |
| currency_code | `string(3)` | default('SAR') |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'is_active'], 'petty_cash_funds_org_active_idx')`
- `$table->index(['organization_id', 'branch_id'], 'petty_cash_funds_org_branch_idx')`

### petty_cash_replenishments

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string(36)` | unique |
| fund_id | `foreignId` | constrained('petty_cash_funds'), cascadeOnDelete |
| replenishment_date | `date` |  |
| amount | `decimal(15, 4)` |  |
| journal_entry_id | `foreignId` | nullable, constrained('journal_entries'), nullOnDelete |
| notes | `text` | nullable |
| requested_by | `foreignId` | constrained('users') |
| approved_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| status | `enum(['requested', 'approved', 'disbursed'])` | default('requested') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['fund_id', 'status'], 'petty_cash_replen_fund_status_idx')`
- `$table->index(['fund_id', 'replenishment_date'], 'petty_cash_replen_fund_date_idx')`

### petty_cash_vouchers

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string(36)` | unique |
| fund_id | `foreignId` | constrained('petty_cash_funds'), cascadeOnDelete |
| voucher_number | `string(30)` | unique |
| voucher_date | `date` |  |
| transaction_type | `enum(['receipt', 'payment'])` |  |
| amount | `decimal(15, 4)` |  |
| description | `string(500)` |  |
| category | `string(100)` | nullable |
| payee_payer | `string(200)` | nullable |
| receipt_number | `string(100)` | nullable |
| account_id | `foreignId` | nullable, constrained('chart_of_accounts'), nullOnDelete |
| approved_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| status | `enum(['draft', 'approved', 'posted', 'cancelled'])` | default('draft') |
| created_by | `foreignId` | constrained('users') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['fund_id', 'voucher_date'], 'petty_cash_vouchers_fund_date_idx')`
- `$table->index(['fund_id', 'status'], 'petty_cash_vouchers_fund_status_idx')`

### petty_cash_transactions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| fund_id | `foreignId` | constrained('petty_cash_funds'), cascadeOnDelete |
| transaction_number | `string(50)` | nullable |
| transaction_date | `date` |  |
| transaction_type | `enum(['receipt', 'payment', 'replenishment'])` | default('payment') |
| amount | `decimal(15, 2)` |  |
| description | `string(500)` | nullable |
| category | `string(100)` | nullable |
| payee_payer | `string(200)` | nullable |
| receipt_reference | `string(100)` | nullable |
| account_id | `unsignedBigInteger` | nullable |
| status | `enum(['pending', 'approved', 'posted', 'cancelled'])` | default('pending') |
| approved_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'fund_id', 'transaction_date'], 'pct_org_fund_date_idx')`
- `$table->index(['organization_id', 'status'], 'pct_org_status_idx')`

### fraud_rules

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| name | `string` |  |
| rule_type | `string` | velocity, amount, geographic, behavioral, pattern |
| entity_type | `string` | invoice, payment, login, contact |
| conditions | `json` | rule-specific condition parameters |
| severity | `string` | low, medium, high, critical |
| is_active | `boolean` | default(true) |
| auto_block | `boolean` | default(false) — block transaction automatically |
| score_impact | `unsignedInteger` | default(10) — fraud score contribution |
| created_by | `foreignId` | constrained('users'), cascadeOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'is_active', 'rule_type'])`

## 0150_hr.php

### appraisal_cycles

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| name | `string` |  |
| review_period_start | `date` |  |
| review_period_end | `date` |  |
| self_review_deadline | `date` | nullable |
| manager_review_deadline | `date` | nullable |
| status | `enum(['draft', 'active', 'self_review', 'manager_review', 'calibration', 'completed', 'cancelled', ])` | default('draft') |
| description | `text` | nullable |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'status'])`

### appraisal_templates

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| name | `string` |  |
| description | `text` | nullable |
| rating_scale | `tinyInteger` | default(5) |
| is_default | `boolean` | default(false) |
| is_active | `boolean` | default(true) |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'is_active'])`

### appraisal_template_sections

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| appraisal_template_id | `foreignId` | constrained('appraisal_templates'), cascadeOnDelete |
| name | `string` |  |
| description | `text` | nullable |
| weight_percent | `decimal(5, 2)` | default(0) |
| sort_order | `smallInteger` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('appraisal_template_id')`

### appraisal_template_questions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| appraisal_template_section_id | `unsignedBigInteger` |  |
| question | `text` |  |
| question_type | `enum(['rating', 'text', 'yes_no', 'multiselect'])` | default('rating') |
| is_required | `boolean` | default(true) |
| sort_order | `smallInteger` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('appraisal_template_section_id')`

Foreign keys:

- `$table->foreign('appraisal_template_section_id', 'apprsl_tmpl_questions_section_fk')->references('id')->on('appraisal_template_sections')->cascadeOnDelete()`

### benefit_types

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| name | `string` |  |
| code | `string(30)` | nullable |
| category | `enum(['allowance', 'insurance', 'other'])` | default('allowance') |
| calculation_type | `enum(['fixed', 'percentage'])` | default('fixed') |
| default_amount | `decimal(15, 4)` | default(0) |
| percentage_basis | `decimal(5, 2)` | nullable |
| is_taxable | `boolean` | default(false) |
| is_active | `boolean` | default(true) |
| eligibility_rules | `json` | nullable |
| description | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'category', 'is_active'], 'ben_typ_org_cat_active_idx')`

### candidates

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| first_name | `string(100)` |  |
| last_name | `string(100)` |  |
| email | `string(200)` |  |
| phone | `string(50)` | nullable |
| linkedin_url | `string(500)` | nullable |
| resume_path | `string(500)` | nullable |
| total_experience_years | `decimal(4, 1)` | default(0) |
| current_company | `string(200)` | nullable |
| current_title | `string(200)` | nullable |
| source | `enum(['job_board', 'referral', 'linkedin', 'direct', 'agency', 'other'])` | default('direct') |
| notes | `text` | nullable |
| deleted_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'email'])`

### compensation_reviews

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string(36)` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| review_name | `string(100)` |  |
| review_date | `date` |  |
| effective_date | `date` |  |
| budget_amount | `decimal(15, 4)` | default(0) |
| allocated_amount | `decimal(15, 4)` | default(0) |
| status | `enum(['draft', 'in_progress', 'approved', 'applied'])` | default('draft') |
| approved_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'status'], 'cr_org_status_idx')`

### departments

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| parent_id | `foreignId` | nullable, constrained('departments'), nullOnDelete |
| name | `string(100)` |  |
| code | `string(20)` | nullable |
| description | `text` | nullable |
| manager_id | `foreignId` | nullable, constrained('users'), nullOnDelete |
| cost_center_id | `foreignId` | nullable, constrained('chart_of_accounts'), nullOnDelete |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'code'])`
- `$table->index(['organization_id', 'is_active'])`

### designations

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| name | `string(100)` |  |
| code | `string(20)` | nullable |
| description | `text` | nullable |
| level | `unsignedTinyInteger` | default(1) — For hierarchy |
| min_salary | `decimal(15, 4)` | nullable |
| max_salary | `decimal(15, 4)` | nullable |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'code'])`
- `$table->index(['organization_id', 'is_active'])`

### employee_exits

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| employee_id | `foreignId` | constrained('employees'), cascadeOnDelete, name('ee_employee_fk') |
| exit_type | `enum(['resignation', 'termination', 'retirement', 'contract_end', 'death'])` | default('resignation') |
| resignation_date | `date` | nullable |
| last_working_date | `date` | nullable |
| notice_period_days | `unsignedSmallInteger` | default(30) |
| notice_period_waived | `boolean` | default(false) |
| exit_reason | `text` | nullable |
| status | `enum(['initiated', 'notice_period', 'clearance_in_progress', 'clearance_complete', 'settled', 'closed'])` | default('initiated') |
| final_settlement_amount | `decimal(18, 4)` | nullable |
| settlement_date | `date` | nullable |
| eosb_amount | `decimal(18, 4)` | nullable |
| leave_encashment_amount | `decimal(18, 4)` | nullable |
| initiated_by | `foreignId` | nullable, constrained('users'), nullOnDelete, name('ee_initiated_by_fk') |
| approved_by | `foreignId` | nullable, constrained('users'), nullOnDelete, name('ee_approved_by_fk') |
| approved_at | `datetime` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'employee_id'], 'ee_org_employee_idx')`
- `$table->index(['organization_id', 'status'], 'ee_org_status_idx')`
- `$table->index(['last_working_date'], 'ee_last_working_date_idx')`

### employees

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| branch_id | `foreignId` | nullable, constrained, nullOnDelete |
| user_id | `foreignId` | nullable, constrained, nullOnDelete |
| employee_number | `string(50)` | nullable |
| first_name | `string(100)` |  |
| middle_name | `string(100)` | nullable |
| last_name | `string(100)` |  |
| display_name | `string(200)` | nullable |
| date_of_birth | `date` | nullable |
| gender | `enum(['male', 'female', 'other'])` | nullable |
| marital_status | `enum(['single', 'married', 'divorced', 'widowed'])` | nullable |
| nationality | `string(50)` | nullable |
| blood_group | `string(5)` | nullable |
| email | `string(200)` | nullable |
| personal_email | `string(200)` | nullable |
| phone | `string(30)` | nullable |
| mobile | `string(30)` | nullable |
| emergency_contact_name | `string(100)` | nullable |
| emergency_contact_phone | `string(30)` | nullable |
| emergency_contact_relation | `string(50)` | nullable |
| address_line_1 | `string(200)` | nullable |
| address_line_2 | `string(200)` | nullable |
| city | `string(100)` | nullable |
| state | `string(100)` | nullable |
| postal_code | `string(20)` | nullable |
| country_code | `string(2)` | nullable |
| department_id | `foreignId` | nullable, constrained, nullOnDelete |
| designation_id | `foreignId` | nullable, constrained, nullOnDelete |
| reporting_manager_id | `foreignId` | nullable, constrained('employees'), nullOnDelete |
| joining_date | `date` | nullable |
| confirmation_date | `date` | nullable |
| termination_date | `date` | nullable |
| termination_reason | `string(500)` | nullable |
| employment_type | `enum(['full_time', 'part_time', 'contract', 'intern', 'probation'])` | default('full_time') |
| employment_status | `enum(['active', 'on_notice', 'terminated', 'resigned', 'absconded'])` | default('active') |
| work_schedule | `string(50)` | nullable — Reference to work schedule |
| shift_start | `time` | nullable |
| shift_end | `time` | nullable |
| work_days | `json` | nullable — ['monday', 'tuesday', ...] |
| national_id | `text` | nullable |
| passport_number | `text` | nullable |
| passport_expiry | `date` | nullable |
| visa_number | `string(50)` | nullable |
| visa_expiry | `date` | nullable |
| work_permit_number | `string(50)` | nullable |
| work_permit_expiry | `date` | nullable |
| tax_number | `string(50)` | nullable — PAN (India), TIN, etc. |
| social_security_number | `string(50)` | nullable — PF number, GOSI, etc. |
| tax_declarations | `json` | nullable — For India HRA, 80C, etc. |
| currency_code | `string(3)` | default('SAR') |
| payment_mode | `string(20)` | default('bank_transfer') |
| bank_name | `string(100)` | nullable |
| bank_account_number | `text` | nullable |
| bank_ifsc_code | `string(20)` | nullable — IFSC for India |
| bank_iban | `text` | nullable — IBAN for GCC |
| notes | `text` | nullable |
| profile_photo_path | `string(500)` | nullable |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |
| rehire_date | `date` | nullable |
| previous_termination_date | `date` | nullable |
| rehire_count | `unsignedTinyInteger` | default(0) |

Indexes:

- `$table->unique(['organization_id', 'employee_number'])`
- `$table->index(['organization_id', 'department_id'])`
- `$table->index(['organization_id', 'employment_status'])`
- `$table->index(['organization_id', 'is_active'])`

### compensation_review_items

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| review_id | `foreignId` | constrained('compensation_reviews'), cascadeOnDelete |
| employee_id | `foreignId` | constrained('employees'), cascadeOnDelete |
| current_salary | `decimal(15, 4)` |  |
| proposed_salary | `decimal(15, 4)` | nullable |
| increase_amount | `decimal(15, 4)` | nullable |
| increase_percentage | `decimal(5, 2)` | nullable |
| adjustment_type | `enum(['merit', 'promotion', 'market_adjustment', 'equity'])` | default('merit') |
| justification | `text` | nullable |
| status | `enum(['pending', 'recommended', 'approved', 'rejected', 'applied'])` | default('pending') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['review_id', 'employee_id'], 'cri_review_emp_idx')`

### employee_benefits

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| employee_id | `foreignId` | constrained('employees'), cascadeOnDelete |
| benefit_type_id | `foreignId` | constrained('benefit_types'), cascadeOnDelete |
| amount | `decimal(15, 4)` | default(0) |
| start_date | `date` |  |
| end_date | `date` | nullable |
| status | `enum(['active', 'suspended', 'terminated'])` | default('active') |
| policy_number | `string(100)` | nullable |
| provider_name | `string(255)` | nullable |
| metadata | `json` | nullable |
| notes | `text` | nullable |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['employee_id', 'status'], 'emp_ben_emp_status_idx')`
- `$table->index(['organization_id', 'benefit_type_id'], 'emp_ben_org_type_idx')`

### benefit_changes

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| employee_benefit_id | `foreignId` | constrained('employee_benefits'), cascadeOnDelete |
| employee_id | `foreignId` | constrained('employees'), cascadeOnDelete |
| change_type | `string(30)` |  |
| old_values | `json` | nullable |
| new_values | `json` | nullable |
| reason | `text` | nullable |
| changed_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| changed_at | `timestamp` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['employee_benefit_id'], 'ben_chg_benefit_idx')`
- `$table->index(['employee_id', 'changed_at'], 'ben_chg_emp_date_idx')`

### employee_dependents

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| employee_id | `foreignId` | constrained('employees'), cascadeOnDelete |
| relationship | `enum(['spouse', 'child', 'parent', 'sibling', 'other'])` |  |
| first_name | `string(100)` |  |
| last_name | `string(100)` |  |
| date_of_birth | `date` | nullable |
| gender | `enum(['male', 'female', 'other'])` | nullable |
| nationality | `char(2)` | nullable |
| id_type | `enum(['national_id', 'passport', 'birth_certificate'])` | nullable |
| id_number | `string(100)` | nullable |
| id_expiry_date | `date` | nullable |
| is_beneficiary | `boolean` | default(false) |
| is_sponsored | `boolean` | default(false) |
| visa_number | `string(50)` | nullable |
| visa_expiry_date | `date` | nullable |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'employee_id'])`
- `$table->index(['organization_id', 'is_beneficiary'])`

### employee_documents

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| employee_id | `foreignId` | constrained, cascadeOnDelete |
| document_type | `string(50)` | passport, id_card, contract, certificate, etc. |
| document_name | `string(200)` |  |
| document_number | `string(100)` | nullable |
| issue_date | `date` | nullable |
| expiry_date | `date` | nullable |
| file_path | `string(500)` | nullable |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['employee_id', 'document_type'])`
- `$table->index('expiry_date')`

### employee_experiences

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| employee_id | `foreignId` | constrained, cascadeOnDelete |
| company_name | `string(200)` |  |
| designation | `string(100)` | nullable |
| from_date | `date` |  |
| to_date | `date` | nullable |
| responsibilities | `text` | nullable |
| reason_for_leaving | `string(500)` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

### employee_loans

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| employee_id | `foreignId` | constrained, cascadeOnDelete |
| loan_number | `string(50)` |  |
| loan_type | `enum(['loan', 'advance', 'salary_advance'])` | default('loan') |
| principal_amount | `decimal(15, 4)` |  |
| interest_rate | `decimal(5, 4)` | default(0) — Annual % |
| disbursement_date | `date` |  |
| repayment_start_date | `date` |  |
| tenure_months | `unsignedSmallInteger` |  |
| emi_amount | `decimal(15, 4)` |  |
| total_repaid | `decimal(15, 4)` | default(0) |
| balance | `decimal(15, 4)` |  |
| status | `enum(['pending', 'active', 'completed', 'cancelled'])` | default('pending') |
| approved_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| approved_at | `datetime` | nullable |
| currency_code | `string(3)` | default('SAR') |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'loan_number'])`
- `$table->index(['organization_id', 'status'])`
- `$table->index(['employee_id', 'status'])`

### employee_qualifications

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| employee_id | `foreignId` | constrained, cascadeOnDelete |
| qualification_type | `string(50)` | degree, diploma, certification, etc. |
| qualification_name | `string(200)` |  |
| institution | `string(200)` | nullable |
| specialization | `string(200)` | nullable |
| year_of_passing | `year` | nullable |
| grade | `string(50)` | nullable |
| file_path | `string(500)` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

### eosb_policies

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| country_code | `string(10)` |  |
| calculation_method | `enum(['saudi', 'uae', 'qatar', 'kuwait', 'bahrain', 'oman', 'india'])` |  |
| min_service_months | `unsignedSmallInteger` | default(12) |
| first_period_days_per_year | `decimal(5, 2)` | default(15.00) |
| first_period_years | `unsignedSmallInteger` | default(5) |
| subsequent_days_per_year | `decimal(5, 2)` | default(30.00) |
| prorate_partial_year | `boolean` | default(true) |
| is_active | `boolean` | default(true) |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'country_code'], 'eosb_pol_org_country_idx')`

### eosb_calculations

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| employee_id | `foreignId` | constrained('employees'), cascadeOnDelete |
| policy_id | `foreignId` | nullable, constrained('eosb_policies'), nullOnDelete |
| calculation_date | `date` |  |
| service_years | `decimal(8, 2)` |  |
| last_basic_salary | `decimal(15, 2)` |  |
| last_total_salary | `decimal(15, 2)` |  |
| gratuity_amount | `decimal(15, 2)` |  |
| deductions | `decimal(15, 2)` | default(0) |
| net_amount | `decimal(15, 2)` |  |
| currency_code | `string(3)` | default('SAR') |
| notes | `text` | nullable |
| status | `enum(['draft', 'approved', 'paid'])` | default('draft') |
| approved_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| approved_at | `timestamp` | nullable |
| paid_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'status'], 'eosb_calc_org_status_idx')`
- `$table->index('employee_id', 'eosb_calc_emp_idx')`

### eosb_provisions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| employee_id | `foreignId` | constrained('employees'), cascadeOnDelete |
| eosb_policy_id | `foreignId` | constrained('eosb_policies'), cascadeOnDelete |
| period_year | `unsignedSmallInteger` |  |
| period_month | `unsignedTinyInteger` |  |
| days_earned | `decimal(8, 4)` | default(0) |
| daily_rate | `decimal(15, 4)` | default(0) |
| provision_amount | `decimal(15, 4)` | default(0) |
| cumulative_amount | `decimal(15, 4)` | default(0) |
| basic_salary_used | `decimal(15, 4)` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['employee_id', 'period_year', 'period_month'], 'eosb_prov_emp_period_uniq')`
- `$table->index(['organization_id', 'period_year', 'period_month'], 'eosb_prov_org_period_idx')`

### eosb_settlements

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| employee_id | `foreignId` | constrained('employees'), cascadeOnDelete |
| eosb_policy_id | `foreignId` | constrained('eosb_policies'), cascadeOnDelete |
| termination_date | `date` |  |
| years_of_service | `decimal(8, 4)` |  |
| total_days_earned | `decimal(8, 4)` |  |
| daily_rate | `decimal(15, 4)` |  |
| gross_amount | `decimal(15, 4)` |  |
| deductions | `decimal(15, 4)` | default(0) |
| net_amount | `decimal(15, 4)` |  |
| currency_code | `string(3)` | default('SAR') |
| payment_date | `date` | nullable |
| status | `enum(['draft', 'approved', 'paid', 'cancelled'])` | default('draft') |
| approved_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| approved_at | `timestamp` | nullable |
| notes | `text` | nullable |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'status'], 'eosb_set_org_status_idx')`
- `$table->index('employee_id', 'eosb_set_emp_idx')`

### exit_clearance_items

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete, name('eci_org_fk') |
| employee_exit_id | `foreignId` | constrained('employee_exits'), cascadeOnDelete, name('eci_exit_fk') |
| department_id | `foreignId` | nullable, constrained('departments'), nullOnDelete, name('eci_dept_fk') |
| clearance_item | `string(100)` |  |
| responsible_person_id | `foreignId` | nullable, constrained('users'), nullOnDelete, name('eci_responsible_fk') |
| status | `enum(['pending', 'cleared', 'waived'])` | default('pending') |
| cleared_at | `datetime` | nullable |
| remarks | `text` | nullable |
| sort_order | `unsignedSmallInteger` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['employee_exit_id'], 'eci_exit_idx')`

### gosi_configurations

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| country_code | `string(10)` | default('SA') |
| name | `string(100)` | nullable |
| employee_contribution_pct | `decimal(6, 2)` | default(0) |
| employer_contribution_pct | `decimal(6, 2)` | default(0) |
| hazard_pct | `decimal(6, 2)` | default(0) |
| salary_ceiling | `decimal(15, 2)` | nullable |
| salary_floor | `decimal(15, 2)` | nullable |
| effective_from | `date` |  |
| effective_to | `date` | nullable |
| is_active | `boolean` | default(true) |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'is_active'], 'gosi_conf_org_active_idx')`
- `$table->index(['organization_id', 'country_code'], 'gosi_conf_org_country_idx')`

### gosi_contributions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| employee_id | `foreignId` | constrained('employees'), cascadeOnDelete |
| period_year | `unsignedSmallInteger` |  |
| period_month | `unsignedTinyInteger` |  |
| basic_salary | `decimal(15, 2)` | default(0) |
| total_salary | `decimal(15, 2)` | default(0) |
| contributable_salary | `decimal(15, 2)` | default(0) |
| employee_contribution | `decimal(15, 2)` | default(0) |
| employer_contribution | `decimal(15, 2)` | default(0) |
| hazard_contribution | `decimal(15, 2)` | default(0) |
| total_contribution | `decimal(15, 2)` | default(0) |
| gosi_id | `string(50)` | nullable |
| status | `enum(['draft', 'submitted', 'paid'])` | default('draft') |
| submitted_at | `timestamp` | nullable |
| paid_at | `timestamp` | nullable |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'employee_id', 'period_year', 'period_month'], 'gosi_contrib_emp_period_uniq')`
- `$table->index(['organization_id', 'period_year', 'period_month'], 'gosi_contrib_org_period_idx')`
- `$table->index(['organization_id', 'status'], 'gosi_contrib_org_status_idx')`

### holidays

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| branch_id | `foreignId` | nullable, constrained, nullOnDelete |
| name | `string(100)` |  |
| holiday_date | `date` |  |
| is_optional | `boolean` | default(false) |
| is_restricted | `boolean` | default(false) — Only for certain religions/groups |
| applicable_to | `string(50)` | nullable — all, specific_department, etc. |
| description | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'holiday_date'])`

### hr_onboardings

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `char(36)` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| employee_id | `foreignId` | constrained('employees'), cascadeOnDelete |
| template_type | `string(50)` | default('standard'), comment('standard \| probation \| rehire \| transfer_in') |
| status | `enum(['pending', 'in_progress', 'completed', 'cancelled'])` | default('pending') |
| started_date | `date` |  |
| target_completion_date | `date` | nullable |
| completed_at | `timestamp` | nullable |
| notes | `text` | nullable |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'status'])`
- `$table->index(['employee_id', 'status'])`

### hr_onboarding_tasks

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| onboarding_id | `foreignId` | constrained('hr_onboardings'), cascadeOnDelete |
| title | `string(255)` |  |
| description | `text` | nullable |
| category | `string(50)` | default('hr'), comment('hr \| it \| manager \| employee \| legal \| finance') |
| due_date | `date` | nullable |
| status | `enum(['pending', 'in_progress', 'done', 'skipped'])` | default('pending') |
| is_required | `boolean` | default(true) |
| sort_order | `tinyInteger` | unsigned, default(0) |
| assigned_to | `foreignId` | nullable, constrained('users'), nullOnDelete |
| completed_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| completed_at | `timestamp` | nullable |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['onboarding_id', 'status'])`
- `$table->index(['assigned_to', 'status'])`

### job_postings

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| branch_id | `foreignId` | nullable, constrained, nullOnDelete |
| department_id | `foreignId` | nullable, constrained('departments'), nullOnDelete |
| designation_id | `foreignId` | nullable, constrained('designations'), nullOnDelete |
| title | `string(200)` |  |
| description | `text` |  |
| requirements | `text` | nullable |
| employment_type | `enum(['full_time', 'part_time', 'contract', 'intern'])` | default('full_time') |
| location | `string(200)` | nullable |
| salary_min | `decimal(12, 2)` | nullable |
| salary_max | `decimal(12, 2)` | nullable |
| currency_code | `string(3)` | default('SAR') |
| vacancies | `unsignedInteger` | default(1) |
| filled_count | `unsignedInteger` | default(0) |
| status | `enum(['draft', 'open', 'on_hold', 'closed', 'cancelled'])` | default('draft') |
| posted_at | `timestamp` | nullable |
| closes_at | `date` | nullable |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| deleted_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'status'])`

### job_applications

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| job_posting_id | `foreignId` | constrained, cascadeOnDelete |
| candidate_id | `foreignId` | constrained, cascadeOnDelete |
| status | `enum(['applied', 'screening', 'shortlisted', 'interview_scheduled', 'interviewed', 'offer_extended', 'offer_accepted', 'offer_declined', 'hired', 'rejected', 'withdrawn', ])` | default('applied') |
| cover_letter | `text` | nullable |
| expected_salary | `decimal(12, 2)` | nullable |
| notice_period_days | `unsignedInteger` | nullable |
| applied_at | `timestamp` | useCurrent |
| reviewed_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| reviewed_at | `timestamp` | nullable |
| rejection_reason | `string(500)` | nullable |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['job_posting_id', 'candidate_id'])`
- `$table->index(['organization_id', 'status'])`
- `$table->index(['job_posting_id', 'status'])`

### interview_schedules

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| job_application_id | `foreignId` | constrained, cascadeOnDelete |
| interview_type | `enum(['phone', 'video', 'in_person', 'technical', 'panel'])` | default('in_person') |
| scheduled_at | `dateTime` |  |
| duration_minutes | `unsignedInteger` | default(60) |
| location | `string(300)` | nullable |
| meeting_link | `string(500)` | nullable |
| interviewers | `json` | nullable |
| status | `enum(['scheduled', 'completed', 'cancelled', 'no_show'])` | default('scheduled') |
| feedback | `text` | nullable |
| rating | `tinyInteger` | unsigned, nullable, comment('1-5') |
| recommendation | `enum(['strong_yes', 'yes', 'neutral', 'no', 'strong_no'])` | nullable |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'job_application_id'])`

### job_offers

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| job_application_id | `foreignId` | constrained, cascadeOnDelete |
| candidate_id | `foreignId` | constrained, cascadeOnDelete |
| job_posting_id | `foreignId` | constrained, cascadeOnDelete |
| offered_salary | `decimal(12, 2)` |  |
| currency_code | `string(3)` | default('SAR') |
| joining_date | `date` | nullable |
| offer_valid_until | `date` | nullable |
| status | `enum(['draft', 'sent', 'accepted', 'declined', 'expired', 'withdrawn'])` | default('draft') |
| terms | `text` | nullable |
| sent_at | `timestamp` | nullable |
| responded_at | `timestamp` | nullable |
| decline_reason | `string(500)` | nullable |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'job_application_id'])`

### key_positions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| department_id | `foreignId` | nullable, constrained('departments'), nullOnDelete |
| title | `string` |  |
| criticality | `enum(['critical', 'high', 'medium'])` | default('high') |
| current_holder_id | `foreignId` | nullable, constrained('employees'), nullOnDelete |
| target_fill_date | `date` | nullable |
| min_successors | `unsignedSmallInteger` | default(2) |
| is_active | `boolean` | default(true) |
| notes | `text` | nullable |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'criticality', 'is_active'], 'key_pos_org_crit_active_idx')`
- `$table->index(['organization_id', 'department_id'], 'key_pos_org_dept_idx')`

### leave_policies

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| name | `string` |  |
| description | `text` | nullable |
| policy_year_type | `string(20)` | default('calendar') — calendar, fiscal, anniversary |
| year_start_date | `date` | nullable — For fiscal/custom year |
| allow_negative_balance | `boolean` | default(false) |
| require_approval | `boolean` | default(true) |
| min_notice_days | `unsignedTinyInteger` | default(0) — Days before leave start |
| allow_half_day | `boolean` | default(true) |
| allow_hourly | `boolean` | default(false) |
| is_default | `boolean` | default(false) |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'is_active'])`

### leave_types

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| leave_policy_id | `foreignId` | nullable, constrained('leave_policies'), nullOnDelete |
| name | `string` |  |
| code | `string(20)` |  |
| description | `text` | nullable |
| annual_quota | `decimal(8, 2)` | default(0) |
| is_paid | `boolean` | default(true) |
| is_encashable | `boolean` | default(false) |
| max_encashable_days | `decimal(8, 2)` | nullable |
| carry_forward | `boolean` | default(false) |
| max_carry_forward_days | `decimal(8, 2)` | nullable |
| min_days_notice | `unsignedSmallInteger` | default(0) |
| max_consecutive_days | `decimal(8, 2)` | nullable |
| requires_attachment | `boolean` | default(false) |
| attachment_required_after_days | `unsignedSmallInteger` | default(0) |
| half_day_allowed | `boolean` | default(true) |
| requires_approval | `boolean` | default(true) |
| applicable_gender | `string(20)` | default('all') |
| applicable_marital_status | `string(20)` | default('all') |
| applicable_after_months | `unsignedSmallInteger` | default(0) |
| employment_type_restriction | `string(50)` | nullable |
| requires_reason | `boolean` | default(false) |
| min_days_per_request | `decimal(8, 2)` | nullable |
| max_days_per_request | `decimal(8, 2)` | nullable |
| allowed_days_of_week | `json` | nullable |
| blackout_dates | `json` | nullable |
| count_holidays | `boolean` | default(false) |
| count_weekends | `boolean` | default(false) |
| accrual_type | `string(20)` | default('annual') — annual, monthly, quarterly, none |
| accrual_day | `unsignedTinyInteger` | nullable |
| prorate_on_joining | `boolean` | default(true) |
| prorate_on_exit | `boolean` | default(true) |
| color | `string(7)` | nullable |
| icon | `string` | nullable |
| sort_order | `unsignedSmallInteger` | default(0) |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'code'])`
- `$table->index(['organization_id', 'is_active'])`

### leave_balances

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| employee_id | `foreignId` | constrained('employees'), cascadeOnDelete |
| leave_type_id | `foreignId` | constrained('leave_types'), cascadeOnDelete |
| leave_tier_id | `unsignedBigInteger` | nullable |
| year | `unsignedSmallInteger` |  |
| opening_balance | `decimal(8, 2)` | default(0) |
| entitled | `decimal(8, 2)` | default(0) |
| accrued | `decimal(8, 2)` | default(0) |
| taken | `decimal(8, 2)` | default(0) |
| adjustment | `decimal(8, 2)` | default(0) |
| encashed | `decimal(8, 2)` | default(0) |
| lapsed | `decimal(8, 2)` | default(0) |
| closing_balance | `decimal(8, 2)` | default(0) |
| last_accrual_date | `date` | nullable |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['employee_id', 'leave_type_id', 'year'])`
- `$table->index(['organization_id', 'year'])`

Added by later migrations:

- `0470_deferred_keys.php`: `$table->foreign('leave_tier_id', 'leave_bal_tier_fk')->references('id')->on('leave_tiers')->nullOnDelete()`

### leave_accruals

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| leave_balance_id | `foreignId` | constrained('leave_balances'), cascadeOnDelete |
| employee_id | `foreignId` | constrained('employees'), cascadeOnDelete |
| accrual_date | `date` |  |
| accrual_type | `string(30)` | monthly, yearly, adjustment, carryforward, opening |
| days | `decimal(6, 2)` |  |
| description | `text` | nullable |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['leave_balance_id', 'accrual_date'])`

### leave_adjustments

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| employee_id | `foreignId` | constrained('employees'), cascadeOnDelete |
| leave_type_id | `foreignId` | constrained('leave_types'), cascadeOnDelete |
| leave_balance_id | `foreignId` | constrained('leave_balances'), cascadeOnDelete |
| adjustment_type | `string(30)` | add, deduct, set, carryforward, encashment |
| days | `decimal(6, 2)` |  |
| balance_before | `decimal(6, 2)` |  |
| balance_after | `decimal(6, 2)` |  |
| reason | `text` |  |
| effective_date | `date` |  |
| approved_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| approved_at | `timestamp` | nullable |
| created_by | `foreignId` | constrained('users'), cascadeOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'employee_id'])`

### leave_requests

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| employee_id | `foreignId` | constrained('employees'), cascadeOnDelete |
| leave_type_id | `foreignId` | constrained('leave_types'), cascadeOnDelete |
| from_date | `date` |  |
| to_date | `date` |  |
| total_days | `decimal(5, 2)` |  |
| is_half_day | `boolean` | default(false) |
| half_day_type | `string(20)` | nullable — first_half, second_half |
| reason | `text` |  |
| contact_during_leave | `string` | nullable |
| address_during_leave | `string` | nullable |
| status | `string(20)` | default('pending') — draft, pending, approved, rejected, cancelled |
| approved_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| approved_at | `timestamp` | nullable |
| rejection_reason | `text` | nullable |
| cancelled_at | `timestamp` | nullable |
| cancellation_reason | `text` | nullable |
| attachment_path | `string` | nullable |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'status'])`
- `$table->index(['employee_id', 'status'])`
- `$table->index(['from_date', 'to_date'])`

## 0160_hr_2.php

### leave_calendar

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| employee_id | `foreignId` | constrained('employees'), cascadeOnDelete |
| leave_request_id | `foreignId` | constrained('leave_requests'), cascadeOnDelete |
| leave_type_id | `foreignId` | constrained('leave_types'), cascadeOnDelete |
| leave_date | `date` |  |
| day_type | `string(20)` | full, first_half, second_half |
| status | `string(20)` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['employee_id', 'leave_date', 'day_type'])`
- `$table->index(['organization_id', 'leave_date'])`
- `$table->index(['leave_request_id'])`

### leave_tiers

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| leave_type_id | `foreignId` | constrained('leave_types'), cascadeOnDelete |
| name | `string` |  |
| description | `text` | nullable |
| min_service_months | `unsignedSmallInteger` | default(0) — 0 = from joining |
| max_service_months | `unsignedSmallInteger` | nullable — NULL = no upper limit |
| employee_grade | `string` | nullable — Specific grade requirement |
| department_id | `string` | nullable — Specific department requirement |
| entitled_days | `decimal(5, 2)` | 21, 28, 30, etc. (decimal for partial days) |
| entitlement_period | `string(20)` | default('yearly') — yearly, monthly |
| monthly_accrual_rate | `decimal(5, 2)` | nullable — Override default accrual |
| max_carryforward_days | `unsignedSmallInteger` | nullable |
| carryforward_expiry_months | `unsignedSmallInteger` | nullable — How long carryforward is valid |
| max_encashable_days | `unsignedSmallInteger` | nullable |
| encashment_rate | `decimal(5, 2)` | nullable — Percentage of daily salary |
| priority | `unsignedSmallInteger` | default(0) — Higher = checked first |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['leave_type_id', 'min_service_months'])`

### leave_tier_approvers

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| leave_tier_id | `foreignId` | constrained('leave_tiers'), cascadeOnDelete |
| user_id | `foreignId` | nullable, constrained, cascadeOnDelete |
| role_id | `foreignId` | nullable, constrained('roles'), cascadeOnDelete |
| designation | `string` | nullable — Alternative to specific user |
| approval_level | `unsignedTinyInteger` | default(1) — For multi-level approval |
| can_approve | `boolean` | default(true) |
| can_reject | `boolean` | default(true) |
| is_final_approver | `boolean` | default(false) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['leave_tier_id', 'approval_level'])`

### manager_delegations

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| manager_id | `foreignId` | constrained('users'), cascadeOnDelete, name('md_manager_fk') |
| delegate_id | `foreignId` | constrained('users'), cascadeOnDelete, name('md_delegate_fk') |
| delegation_type | `enum(['full', 'leave_approval', 'attendance_approval', 'expense_approval'])` | default('full') |
| valid_from | `date` |  |
| valid_to | `date` | nullable |
| is_active | `boolean` | default(true) |
| reason | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['manager_id', 'is_active'], 'md_manager_active_idx')`
- `$table->index(['delegate_id', 'is_active'], 'md_delegate_active_idx')`

### manager_team_views

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete, name('mtv_org_fk') |
| manager_id | `foreignId` | constrained('users'), cascadeOnDelete, name('mtv_manager_fk') |
| employee_id | `foreignId` | constrained('employees'), cascadeOnDelete, name('mtv_employee_fk') |
| relationship_type | `enum(['direct_report', 'indirect_report'])` | default('direct_report') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['manager_id', 'employee_id'], 'mtv_manager_employee_unq')`

### off_cycle_payroll_items

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete, name('ocpi_org_fk') |
| off_cycle_payroll_run_id | `foreignId` | constrained('off_cycle_payroll_runs'), cascadeOnDelete, name('ocpi_run_fk') |
| employee_id | `foreignId` | constrained('employees'), cascadeOnDelete, name('ocpi_employee_fk') |
| component_code | `string(50)` |  |
| component_name | `string(100)` |  |
| amount | `decimal(18, 4)` |  |
| tax_amount | `decimal(18, 4)` | default(0) |
| net_amount | `decimal(18, 4)` |  |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['off_cycle_payroll_run_id'], 'ocpi_run_idx')`
- `$table->index(['employee_id'], 'ocpi_employee_idx')`

### off_cycle_payroll_runs

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| run_type | `enum(['bonus', 'termination', 'correction', 'advance_recovery', 'other'])` | default('bonus') |
| run_name | `string(100)` |  |
| run_date | `date` |  |
| status | `enum(['draft', 'processing', 'completed', 'cancelled'])` | default('draft') |
| employee_count | `unsignedInteger` | default(0) |
| total_gross | `decimal(18, 4)` | default(0) |
| total_net | `decimal(18, 4)` | default(0) |
| notes | `text` | nullable |
| processed_by | `foreignId` | nullable, constrained('users'), nullOnDelete, name('ocpr_processed_by_fk') |
| processed_at | `datetime` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'run_date'], 'ocpr_org_date_idx')`

### om_tasks

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| task_code | `string(20)` |  |
| name | `string(255)` |  |
| description | `text` | nullable |
| task_type | `enum(['function', 'activity', 'responsibility'])` | default('function') |
| is_active | `boolean` | default(true) |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'task_code'])`
- `$table->index(['organization_id', 'is_active'])`

### overtime_policies

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| policy_name | `string(100)` |  |
| daily_standard_hours | `decimal(5, 2)` | default(8) |
| weekly_standard_hours | `decimal(6, 2)` | default(40) |
| ot_rate_weekday | `decimal(5, 2)` | default(1.5) |
| ot_rate_weekend | `decimal(5, 2)` | default(2.0) |
| ot_rate_holiday | `decimal(5, 2)` | default(2.5) |
| max_daily_ot_hours | `decimal(5, 2)` | default(4) |
| max_weekly_ot_hours | `decimal(6, 2)` | default(12) |
| requires_approval | `boolean` | default(true) |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'is_active'], 'op_org_active_idx')`

### overtime_requests

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string(36)` | unique |
| employee_id | `foreignId` | constrained('employees'), cascadeOnDelete |
| policy_id | `foreignId` | constrained('overtime_policies'), cascadeOnDelete |
| ot_date | `date` |  |
| ot_start | `time` |  |
| ot_end | `time` |  |
| ot_hours | `decimal(5, 2)` |  |
| reason | `string(500)` | nullable |
| day_type | `enum(['weekday', 'weekend', 'holiday'])` | default('weekday') |
| ot_rate | `decimal(5, 2)` |  |
| ot_amount | `decimal(15, 4)` | default(0) |
| status | `enum(['pending', 'approved', 'rejected', 'paid'])` | default('pending') |
| approved_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_by | `foreignId` | constrained('users') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['employee_id', 'status'], 'or_emp_status_idx')`
- `$table->index(['employee_id', 'ot_date'], 'or_emp_date_idx')`

### pay_grades

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| grade_code | `string(20)` |  |
| grade_name | `string(100)` |  |
| min_salary | `decimal(15, 4)` |  |
| mid_salary | `decimal(15, 4)` |  |
| max_salary | `decimal(15, 4)` |  |
| currency_code | `string(3)` | default('SAR') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'grade_code'], 'pg_org_code_unique')`

### payroll_corrections

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| employee_id | `foreignId` | constrained('employees'), cascadeOnDelete, name('pc_employee_fk') |
| original_payroll_period_id | `foreignId` | constrained('payroll_periods'), cascadeOnDelete, name('pc_orig_period_fk') |
| correction_payroll_period_id | `foreignId` | nullable, constrained('payroll_periods'), nullOnDelete, name('pc_corr_period_fk') |
| correction_type | `enum(['salary_change', 'component_adjustment', 'tax_correction', 'deduction_adjustment'])` | default('salary_change') |
| status | `enum(['draft', 'approved', 'posted', 'cancelled'])` | default('draft') |
| original_amount | `decimal(18, 4)` |  |
| corrected_amount | `decimal(18, 4)` |  |
| difference_amount | `decimal(18, 4)` |  |
| reason | `text` | nullable |
| approved_by | `foreignId` | nullable, constrained('users'), nullOnDelete, name('pc_approved_by_fk') |
| approved_at | `datetime` | nullable |
| posted_at | `datetime` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'employee_id'], 'pc_org_employee_idx')`
- `$table->index(['original_payroll_period_id'], 'pc_orig_period_idx')`

### payroll_periods

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| name | `string(50)` |  |
| start_date | `date` |  |
| end_date | `date` |  |
| payment_date | `date` | nullable |
| status | `enum(['open', 'processing', 'processed', 'closed'])` | default('open') |
| processed_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| processed_at | `datetime` | nullable |
| closed_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| closed_at | `datetime` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'start_date', 'end_date'])`
- `$table->index(['organization_id', 'status'])`

### epf_contributions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| employee_id | `foreignId` | constrained('employees'), cascadeOnDelete |
| payroll_period_id | `foreignId` | constrained('payroll_periods'), cascadeOnDelete |
| uan | `string(12)` | nullable — Universal Account Number (EPFO) |
| pf_wage | `decimal(12, 2)` | PF wage (basic + DA, capped at 15000) |
| employee_contribution | `decimal(12, 2)` | 12% of PF wage |
| employer_epf_contribution | `decimal(12, 2)` | 3.67% diff after EPS |
| employer_eps_contribution | `decimal(12, 2)` | 8.33% EPS (max ₹1250/month) |
| edli_contribution | `decimal(12, 2)` | 0.50% EDLI employer |
| admin_charges | `decimal(12, 2)` | default(0) — 0.50% EPF admin |
| status | `enum(['draft', 'submitted', 'challan_paid'])` | default('draft') |
| challan_number | `string(50)` | nullable |
| challan_due_date | `date` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'employee_id', 'payroll_period_id'], 'epf_contrib_org_emp_period_uniq')`
- `$table->index(['organization_id', 'payroll_period_id'], 'epf_contrib_org_period_idx')`

### esi_contributions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| employee_id | `foreignId` | constrained('employees'), cascadeOnDelete |
| payroll_period_id | `foreignId` | constrained('payroll_periods'), cascadeOnDelete |
| ip_number | `string(17)` | nullable — Insurance Policy Number (ESIC) |
| gross_wage | `decimal(12, 2)` |  |
| employee_contribution | `decimal(12, 2)` | 0.75% of gross wage |
| employer_contribution | `decimal(12, 2)` | 3.25% of gross wage |
| is_applicable | `boolean` | default(true) — false when gross > ₹21,000 |
| status | `enum(['draft', 'submitted', 'challan_paid'])` | default('draft') |
| challan_number | `string(50)` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'employee_id', 'payroll_period_id'], 'esi_contrib_org_emp_period_uniq')`
- `$table->index(['organization_id', 'payroll_period_id'], 'esi_contrib_org_period_idx')`

### leave_encashments

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| employee_id | `foreignId` | constrained('employees'), cascadeOnDelete |
| leave_type_id | `foreignId` | constrained('leave_types'), cascadeOnDelete |
| leave_balance_id | `foreignId` | constrained('leave_balances'), cascadeOnDelete |
| requested_days | `decimal(5, 2)` |  |
| approved_days | `decimal(5, 2)` | nullable |
| daily_rate | `decimal(15, 2)` | Employee's daily salary |
| encashment_rate | `decimal(5, 2)` | Percentage (100 = full) |
| amount | `decimal(15, 2)` | nullable |
| status | `string(20)` | default('pending') — pending, approved, rejected, paid |
| approved_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| approved_at | `timestamp` | nullable |
| payroll_id | `foreignId` | nullable — Link to payroll when paid |
| notes | `text` | nullable |
| created_by | `foreignId` | constrained('users'), cascadeOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'status'])`

Foreign keys:

- `$table->foreign('payroll_id', 'leave_encash_payroll_period_fk')->references('id')->on('payroll_periods')->nullOnDelete()`

### per_diem_rates

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| destination_country | `string(3)` | ISO country code |
| destination_city | `string(100)` | nullable |
| daily_allowance | `decimal(15, 4)` |  |
| currency_code | `string(3)` | default('SAR') |
| meal_allowance_type | `enum(['included', 'separate'])` | default('included') |
| meal_breakfast | `decimal(15, 4)` | default(0) |
| meal_lunch | `decimal(15, 4)` | default(0) |
| meal_dinner | `decimal(15, 4)` | default(0) |
| mileage_rate | `decimal(10, 4)` | default(0) |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'destination_country', 'destination_city'], 'pdr_org_dest_unique')`

### performance_appraisals

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| appraisal_cycle_id | `foreignId` | constrained('appraisal_cycles'), cascadeOnDelete |
| employee_id | `foreignId` | constrained('employees'), cascadeOnDelete |
| reviewer_id | `foreignId` | nullable, constrained('employees'), nullOnDelete |
| appraisal_template_id | `foreignId` | nullable, constrained('appraisal_templates'), nullOnDelete |
| status | `enum(['pending', 'self_review_submitted', 'manager_review_submitted', 'acknowledged', 'completed', ])` | default('pending') |
| self_submitted_at | `timestamp` | nullable |
| manager_submitted_at | `timestamp` | nullable |
| acknowledged_at | `timestamp` | nullable |
| overall_self_rating | `decimal(3, 2)` | nullable |
| overall_manager_rating | `decimal(3, 2)` | nullable |
| final_rating | `decimal(3, 2)` | nullable |
| self_comments | `text` | nullable |
| manager_comments | `text` | nullable |
| employee_acknowledgement | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['appraisal_cycle_id', 'employee_id'])`
- `$table->index(['organization_id', 'status'])`

### appraisal_responses

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| performance_appraisal_id | `foreignId` | constrained('performance_appraisals'), cascadeOnDelete |
| appraisal_template_question_id | `foreignId` | constrained('appraisal_template_questions'), cascadeOnDelete |
| respondent_type | `enum(['self', 'manager'])` |  |
| rating | `tinyInteger` | nullable |
| text_response | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['performance_appraisal_id', 'respondent_type'], 'appraisal_resp_appraisal_type_idx')`

### appraisal_reviewers

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| appraisal_id | `foreignId` | constrained('performance_appraisals'), cascadeOnDelete |
| reviewer_id | `foreignId` | constrained('employees'), cascadeOnDelete |
| reviewer_type | `string(50)` | comment('self, peer, subordinate, manager, external') |
| status | `string(50)` | default('pending'), comment('pending, in_progress, submitted, declined') |
| submitted_at | `timestamp` | nullable |
| overall_rating | `decimal(3, 2)` | nullable |
| strengths | `text` | nullable |
| improvements | `text` | nullable |
| comments | `text` | nullable |
| is_anonymous | `boolean` | default(false) |
| due_date | `date` | nullable |
| reminder_sent_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['appraisal_id', 'status'])`
- `$table->index(['reviewer_id', 'status'])`
- `$table->unique(['appraisal_id', 'reviewer_id', 'reviewer_type'], 'appr_reviewer_appraisal_reviewer_type_unq')`

### appraisal_reviewer_responses

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| appraisal_reviewer_id | `foreignId` | constrained('appraisal_reviewers'), cascadeOnDelete |
| question_id | `foreignId` | nullable, constrained('appraisal_template_questions'), nullOnDelete |
| question_text | `string` | comment('Denormalised in case the template changes after submission') |
| rating | `decimal(3, 2)` | nullable |
| response_text | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('appraisal_reviewer_id')`

### performance_goals

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| employee_id | `foreignId` | constrained('employees'), cascadeOnDelete |
| appraisal_cycle_id | `foreignId` | nullable, constrained('appraisal_cycles'), nullOnDelete |
| title | `string` |  |
| description | `text` | nullable |
| target_date | `date` | nullable |
| weight_percent | `decimal(5, 2)` | default(0) |
| status | `enum(['draft', 'active', 'completed', 'cancelled'])` | default('draft') |
| progress_percent | `tinyInteger` | default(0) |
| self_rating | `tinyInteger` | nullable |
| manager_rating | `tinyInteger` | nullable |
| self_comments | `text` | nullable |
| manager_comments | `text` | nullable |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| deleted_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'employee_id', 'status'])`

### performance_goal_updates

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| performance_goal_id | `foreignId` | constrained('performance_goals'), cascadeOnDelete |
| updated_by | `foreignId` | constrained('users'), cascadeOnDelete |
| progress_percent | `tinyInteger` |  |
| notes | `text` | nullable |
| created_at | `timestamp` | useCurrent |

Indexes:

- `$table->index('performance_goal_id')`

### personnel_actions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| action_number | `string` | unique — PA-2026-00001 (SAP PA40 action number) |
| employee_id | `unsignedBigInteger` |  |
| action_type | `string` | hire\|transfer\|promotion\|demotion\|exit\|rehire\|leave_of_absence |
| effective_date | `date` |  |
| status | `string` | default('draft') — draft\|submitted\|approved\|completed\|reversed\|rejected |
| payload | `json` | nullable — action-specific data (new dept, new salary, etc.) |
| reason | `string` | nullable |
| notes | `text` | nullable |
| rejection_reason | `string` | nullable |
| initiated_by | `unsignedBigInteger` |  |
| approved_by | `unsignedBigInteger` | nullable |
| approved_at | `timestamp` | nullable |
| completed_at | `timestamp` | nullable |
| reversed_at | `timestamp` | nullable |
| reversed_by | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'employee_id'])`
- `$table->index(['organization_id', 'status'])`

### personnel_action_steps

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| personnel_action_id | `unsignedBigInteger` |  |
| step_name | `string` | e.g. update_position, update_salary, notify_payroll |
| status | `string` | default('pending') — pending\|completed\|failed\|skipped |
| result | `json` | nullable |
| error_message | `string` | nullable |
| executed_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['personnel_action_id', 'status'])`

Foreign keys:

- `$table->foreign('personnel_action_id')->references('id')->on('personnel_actions')->cascadeOnDelete()`

### positions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string(36)` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| position_code | `string(20)` |  |
| position_title | `string(150)` |  |
| department_id | `foreignId` | nullable, constrained('departments'), nullOnDelete |
| designation_id | `foreignId` | nullable, constrained('designations'), nullOnDelete |
| pay_grade_id | `foreignId` | nullable, constrained('pay_grades'), nullOnDelete |
| reports_to_position_id | `unsignedBigInteger` | nullable — self-referential |
| headcount_authorized | `integer` | default(1) |
| headcount_filled | `integer` | default(0) |
| is_key_position | `boolean` | default(false) |
| status | `enum(['active', 'frozen', 'abolished'])` | default('active') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'position_code'], 'pos_org_code_unique')`
- `$table->index(['organization_id', 'department_id'], 'pos_org_dept_idx')`

### employee_transfers

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| employee_id | `foreignId` | constrained('employees'), cascadeOnDelete |
| transfer_number | `string(50)` |  |
| effective_date | `date` |  |
| transfer_type | `enum(['department', 'position', 'designation', 'location', 'manager', 'lateral', 'promotion', 'demotion', ])` |  |
| reason | `string(500)` | nullable |
| from_department_id | `foreignId` | nullable, constrained('departments'), nullOnDelete |
| to_department_id | `foreignId` | nullable, constrained('departments'), nullOnDelete |
| from_designation_id | `foreignId` | nullable, constrained('designations'), nullOnDelete |
| to_designation_id | `foreignId` | nullable, constrained('designations'), nullOnDelete |
| from_position_id | `foreignId` | nullable, constrained('positions'), nullOnDelete |
| to_position_id | `foreignId` | nullable, constrained('positions'), nullOnDelete |
| from_reporting_manager_id | `foreignId` | nullable, constrained('employees'), nullOnDelete |
| to_reporting_manager_id | `foreignId` | nullable, constrained('employees'), nullOnDelete |
| from_branch_id | `foreignId` | nullable, constrained('branches'), nullOnDelete |
| to_branch_id | `foreignId` | nullable, constrained('branches'), nullOnDelete |
| status | `enum(['draft', 'pending_approval', 'approved', 'rejected', 'applied', ])` | default('draft') |
| initiated_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| approved_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| rejected_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| approved_at | `timestamp` | nullable |
| rejected_at | `timestamp` | nullable |
| applied_at | `timestamp` | nullable |
| rejection_reason | `text` | nullable |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'transfer_number'])`
- `$table->index(['organization_id', 'employee_id'])`
- `$table->index(['organization_id', 'status'])`
- `$table->index(['organization_id', 'effective_date'])`

### om_position_tasks

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| position_id | `foreignId` | constrained('positions'), cascadeOnDelete |
| task_id | `foreignId` | constrained('om_tasks'), cascadeOnDelete |
| responsibility_level | `enum(['primary', 'secondary', 'additional'])` | default('primary') |
| valid_from | `date` | nullable |
| valid_to | `date` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['position_id', 'task_id'])`
- `$table->index(['organization_id', 'position_id'])`

### probation_periods

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| employee_id | `foreignId` | constrained('employees'), cascadeOnDelete, name('pp_employee_fk') |
| start_date | `date` |  |
| end_date | `date` |  |
| extended_end_date | `date` | nullable |
| status | `enum(['active', 'completed', 'extended', 'failed', 'waived'])` | default('active') |
| review_date | `date` | nullable |
| outcome | `enum(['confirmed', 'extended', 'terminated'])` | nullable |
| outcome_date | `date` | nullable |
| reviewer_id | `foreignId` | nullable, constrained('users'), nullOnDelete, name('pp_reviewer_fk') |
| review_notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'employee_id'], 'probation_org_employee_idx')`
- `$table->index(['organization_id', 'status'], 'probation_org_status_idx')`
- `$table->index(['end_date'], 'probation_end_date_idx')`

### professional_tax_configs

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| state_code | `string(2)` | ISO IN-state: KA, MH, WB, TN, AP, TS... |
| salary_from | `decimal(12, 2)` |  |
| salary_to | `decimal(12, 2)` | nullable — null = no upper limit |
| monthly_tax | `decimal(8, 2)` |  |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'state_code'], 'pt_config_org_state_idx')`

### public_holidays

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| branch_id | `foreignId` | nullable, constrained, nullOnDelete |
| name | `string` |  |
| holiday_date | `date` |  |
| country_code | `string(3)` | nullable |
| state_code | `string(10)` | nullable |
| is_recurring | `boolean` | default(false) |
| is_optional | `boolean` | default(false) |
| year | `unsignedSmallInteger` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'holiday_date', 'branch_id'])`
- `$table->index(['organization_id', 'year'])`

### salary_components

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| name | `string(100)` |  |
| code | `string(20)` |  |
| description | `text` | nullable |
| type | `enum(['earning', 'deduction'])` | default('earning') |
| category | `enum(['basic', 'allowance', 'bonus', 'reimbursement', 'statutory_deduction', 'voluntary_deduction', 'tax', ])` | default('allowance') |
| calculation_type | `enum(['fixed', 'percentage', 'formula'])` | default('fixed') |
| default_value | `decimal(15, 4)` | default(0) |
| percentage_of | `string(50)` | nullable — Component code to calculate percentage of |
| formula | `string(500)` | nullable |
| is_taxable | `boolean` | default(true) |
| is_pro_rata | `boolean` | default(true) — Based on days worked |
| is_statutory | `boolean` | default(false) |
| is_flexible_benefit | `boolean` | default(false) — Part of flexible benefit plan |
| show_in_payslip | `boolean` | default(true) |
| sort_order | `unsignedSmallInteger` | default(0) |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'code'])`

### salary_structures

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| name | `string(100)` |  |
| code | `string(20)` |  |
| description | `text` | nullable |
| currency_code | `string(3)` | default('SAR') |
| payroll_frequency | `enum(['monthly', 'bi_weekly', 'weekly'])` | default('monthly') |
| is_default | `boolean` | default(false) |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'code'])`

### employee_salaries

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| employee_id | `foreignId` | constrained, cascadeOnDelete |
| salary_structure_id | `foreignId` | constrained, cascadeOnDelete |
| effective_from | `date` |  |
| effective_to | `date` | nullable |
| ctc | `decimal(15, 4)` | default(0) — Cost to company (annual) |
| gross_salary | `decimal(15, 4)` | default(0) — Monthly gross |
| net_salary | `decimal(15, 4)` | default(0) — Monthly net |
| currency_code | `string(3)` | default('SAR') |
| reason_for_change | `string(500)` | nullable |
| is_current | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['employee_id', 'is_current'])`
- `$table->index(['employee_id', 'effective_from'])`

### employee_salary_components

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| employee_salary_id | `foreignId` | constrained, cascadeOnDelete |
| salary_component_id | `foreignId` | constrained, cascadeOnDelete |
| amount | `decimal(15, 4)` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['employee_salary_id', 'salary_component_id'], 'emp_salary_component_unique')`

### payslips

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| payroll_period_id | `foreignId` | constrained, cascadeOnDelete |
| employee_id | `foreignId` | constrained, cascadeOnDelete |
| employee_salary_id | `foreignId` | constrained, cascadeOnDelete |
| payslip_number | `string(50)` |  |
| payment_date | `date` | nullable |
| total_working_days | `decimal(5, 2)` | default(0) |
| days_worked | `decimal(5, 2)` | default(0) |
| days_on_leave | `decimal(5, 2)` | default(0) |
| unpaid_leave_days | `decimal(5, 2)` | default(0) |
| overtime_hours | `decimal(6, 2)` | default(0) |
| gross_earnings | `decimal(15, 4)` | default(0) |
| total_deductions | `decimal(15, 4)` | default(0) |
| net_salary | `decimal(15, 4)` | default(0) |
| currency_code | `string(3)` | default('SAR') |
| taxable_income | `decimal(15, 4)` | default(0) |
| tax_deducted | `decimal(15, 4)` | default(0) |
| status | `enum(['draft', 'pending', 'approved', 'paid', 'cancelled'])` | default('draft') |
| approved_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| approved_at | `datetime` | nullable |
| payment_mode | `string(20)` | nullable |
| payment_reference | `string(100)` | nullable |
| paid_at | `datetime` | nullable |
| journal_entry_id | `foreignId` | nullable, constrained('journal_entries'), nullOnDelete |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'payslip_number'])`
- `$table->unique(['payroll_period_id', 'employee_id'])`
- `$table->index(['organization_id', 'status'])`
- `$table->index(['employee_id', 'status'], 'ps_employee_status_idx')`

### loan_repayments

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| employee_loan_id | `foreignId` | constrained, cascadeOnDelete |
| payslip_id | `foreignId` | nullable, constrained, nullOnDelete |
| installment_number | `unsignedSmallInteger` |  |
| due_date | `date` |  |
| principal_amount | `decimal(15, 4)` |  |
| interest_amount | `decimal(15, 4)` | default(0) |
| total_amount | `decimal(15, 4)` |  |
| amount_paid | `decimal(15, 4)` | default(0) |
| paid_date | `date` | nullable |
| status | `enum(['pending', 'paid', 'partial', 'skipped'])` | default('pending') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['employee_loan_id', 'status'])`
- `$table->index('due_date')`

### payslip_items

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| payslip_id | `foreignId` | constrained, cascadeOnDelete |
| salary_component_id | `foreignId` | nullable, constrained, cascadeOnDelete |
| type | `enum(['earning', 'deduction'])` |  |
| name | `string(100)` |  |
| amount | `decimal(15, 4)` | default(0) |
| ytd_amount | `decimal(15, 4)` | default(0) — Year to date |
| sort_order | `unsignedSmallInteger` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| reference_type | `string` | nullable |
| reference_id | `unsignedBigInteger` | nullable |

Indexes:

- `$table->index('payslip_id')`
- `$table->index(['reference_type', 'reference_id'])`

### salary_structure_components

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| salary_structure_id | `foreignId` | constrained, cascadeOnDelete |
| salary_component_id | `foreignId` | constrained, cascadeOnDelete |
| calculation_type | `enum(['fixed', 'percentage', 'formula'])` | nullable |
| value | `decimal(15, 4)` | default(0) |
| percentage_of | `string(50)` | nullable |
| formula | `string(500)` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['salary_structure_id', 'salary_component_id'], 'structure_component_unique')`

### shift_patterns

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| name | `string` |  |
| code | `string(20)` | nullable |
| start_time | `time` |  |
| end_time | `time` |  |
| break_minutes | `unsignedSmallInteger` | default(0) |
| days_of_week | `json` |  |
| crosses_midnight | `boolean` | default(false) |
| color_hex | `string(7)` | nullable |
| is_active | `boolean` | default(true) |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'is_active'], 'shft_pat_org_active_idx')`

## 0170_hr_3.php

### shift_rosters

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| branch_id | `foreignId` | nullable, constrained('branches'), nullOnDelete |
| department_id | `foreignId` | nullable, constrained('departments'), nullOnDelete |
| name | `string` |  |
| roster_period_start | `date` |  |
| roster_period_end | `date` |  |
| status | `enum(['draft', 'published', 'archived'])` | default('draft') |
| published_at | `timestamp` | nullable |
| published_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| notes | `text` | nullable |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'status'], 'shft_ros_org_status_idx')`
- `$table->index(['organization_id', 'roster_period_start', 'roster_period_end'], 'shft_ros_org_period_idx')`

### shift_roster_lines

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| roster_id | `foreignId` | constrained('shift_rosters'), cascadeOnDelete |
| employee_id | `foreignId` | constrained('employees'), cascadeOnDelete |
| shift_pattern_id | `foreignId` | nullable, constrained('shift_patterns'), nullOnDelete |
| shift_date | `date` |  |
| is_day_off | `boolean` | default(false) |
| override_start_time | `time` | nullable |
| override_end_time | `time` | nullable |
| notes | `string(500)` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['roster_id', 'employee_id', 'shift_date'], 'shft_line_ros_emp_date_uniq')`
- `$table->index(['employee_id', 'shift_date'], 'shft_line_emp_date_idx')`

### shift_swap_requests

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| requester_id | `foreignId` | constrained('employees'), cascadeOnDelete |
| requested_employee_id | `foreignId` | constrained('employees'), cascadeOnDelete |
| requester_roster_line_id | `foreignId` | nullable, constrained('shift_roster_lines'), nullOnDelete |
| requested_roster_line_id | `foreignId` | nullable, constrained('shift_roster_lines'), nullOnDelete |
| requester_shift_date | `date` |  |
| requested_shift_date | `date` |  |
| reason | `string(500)` | nullable |
| status | `enum(['pending', 'accepted', 'rejected', 'approved', 'cancelled'])` | default('pending') |
| approved_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| approved_at | `timestamp` | nullable |
| rejection_reason | `string(500)` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'status'], 'shft_swp_org_status_idx')`
- `$table->index(['requester_id', 'status'], 'shft_swp_req_status_idx')`
- `$table->index(['requested_employee_id', 'status'], 'shft_swp_reqd_status_idx')`

### shifts

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| name | `string(100)` |  |
| code | `string(20)` | nullable |
| start_time | `time` |  |
| end_time | `time` |  |
| break_minutes | `unsignedSmallInteger` | default(0) |
| is_overnight | `boolean` | default(false) |
| is_flexible | `boolean` | default(false) |
| flexible_start_window_minutes | `unsignedSmallInteger` | default(0) |
| overtime_eligible | `boolean` | default(false) |
| color_hex | `string(7)` | nullable |
| notes | `text` | nullable |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'is_active'], 'shifts_org_active_idx')`

### employee_shift_assignments

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| employee_id | `foreignId` | constrained('employees'), cascadeOnDelete |
| shift_id | `foreignId` | constrained('shifts'), cascadeOnDelete |
| effective_from | `date` |  |
| effective_to | `date` | nullable |
| notes | `string(500)` | nullable |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'employee_id'], 'esa_org_emp_idx')`
- `$table->index(['employee_id', 'effective_from', 'effective_to'], 'esa_emp_period_idx')`

### skill_categories

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| name | `string` |  |
| description | `text` | nullable |
| parent_id | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade')`
- `$table->foreign('parent_id', 'skill_cat_parent_fk')->references('id')->on('skill_categories')->onDelete('set null')`

### skills

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| skill_category_id | `unsignedBigInteger` |  |
| name | `string` |  |
| description | `text` | nullable |
| proficiency_scale | `unsignedTinyInteger` | default(5) — 1-5 or 1-10 |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade')`
- `$table->foreign('skill_category_id', 'skill_cat_fk')->references('id')->on('skill_categories')->onDelete('restrict')`

### social_insurance_schemes

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| name | `string` |  |
| country_code | `string(10)` |  |
| scheme_code | `string(20)` | nullable |
| employee_contribution_pct | `decimal(5, 2)` | default(0) |
| employer_contribution_pct | `decimal(5, 2)` | default(0) |
| work_hazard_pct | `decimal(5, 2)` | default(0) |
| applicable_to | `enum(['all', 'nationals_only', 'expats_only'])` | default('all') |
| salary_ceiling | `decimal(15, 4)` | nullable |
| salary_floor | `decimal(15, 4)` | nullable |
| is_active | `boolean` | default(true) |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'country_code'], 'si_sch_org_country_idx')`

### social_insurance_records

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| employee_id | `foreignId` | constrained('employees'), cascadeOnDelete |
| scheme_id | `foreignId` | constrained('social_insurance_schemes'), cascadeOnDelete |
| employee_number_si | `string(50)` | nullable |
| enrollment_date | `date` |  |
| termination_date | `date` | nullable |
| status | `enum(['active', 'suspended', 'terminated'])` | default('active') |
| insurable_salary | `decimal(15, 4)` | nullable |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['employee_id', 'scheme_id'], 'si_rec_emp_scheme_uniq')`
- `$table->index(['organization_id', 'status'], 'si_rec_org_status_idx')`

### social_insurance_submissions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| scheme_id | `foreignId` | constrained('social_insurance_schemes'), cascadeOnDelete |
| period_year | `unsignedSmallInteger` |  |
| period_month | `unsignedTinyInteger` |  |
| total_employees | `unsignedInteger` | default(0) |
| total_insurable_salary | `decimal(15, 4)` | default(0) |
| total_employee_contrib | `decimal(15, 4)` | default(0) |
| total_employer_contrib | `decimal(15, 4)` | default(0) |
| total_work_hazard_contrib | `decimal(15, 4)` | default(0) |
| total_amount | `decimal(15, 4)` | default(0) |
| status | `enum(['draft', 'submitted', 'acknowledged', 'rejected'])` | default('draft') |
| reference_number | `string(100)` | nullable |
| submitted_at | `timestamp` | nullable |
| submitted_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'scheme_id', 'period_year', 'period_month'], 'si_sub_org_sch_period_uniq')`
- `$table->index(['organization_id', 'status'], 'si_sub_org_status_idx')`

### social_insurance_submission_lines

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| submission_id | `foreignId` | constrained('social_insurance_submissions'), cascadeOnDelete |
| employee_id | `foreignId` | constrained('employees'), cascadeOnDelete |
| record_id | `foreignId` | constrained('social_insurance_records'), cascadeOnDelete |
| employee_number_si | `string(50)` | nullable |
| insurable_salary | `decimal(15, 4)` | default(0) |
| employee_contribution | `decimal(15, 4)` | default(0) |
| employer_contribution | `decimal(15, 4)` | default(0) |
| work_hazard_contribution | `decimal(15, 4)` | default(0) |
| total_contribution | `decimal(15, 4)` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['submission_id', 'employee_id'], 'si_line_sub_emp_idx')`

### succession_candidates

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| key_position_id | `foreignId` | constrained('key_positions'), cascadeOnDelete |
| employee_id | `foreignId` | constrained('employees'), cascadeOnDelete |
| readiness | `enum(['ready_now', 'one_two_years', 'three_five_years'])` | default('three_five_years') |
| performance_rating | `unsignedTinyInteger` | nullable, comment('1-5 rating') |
| potential_rating | `unsignedTinyInteger` | nullable, comment('1-5 rating') |
| nominated_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| nomination_date | `date` | nullable |
| last_reviewed_at | `date` | nullable |
| is_active | `boolean` | default(true) |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['key_position_id', 'employee_id'], 'succ_cand_pos_emp_uniq')`
- `$table->index(['employee_id', 'readiness'], 'succ_cand_emp_ready_idx')`
- `$table->index(['key_position_id', 'readiness'], 'succ_cand_pos_ready_idx')`

### succession_plans

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| title | `string` |  |
| description | `text` | nullable |
| current_employee_id | `foreignId` | constrained('employees'), cascadeOnDelete |
| position_title | `string` | nullable |
| criticality | `string(20)` | default('medium') — critical, high, medium, low |
| status | `string(20)` | default('active') — active, inactive, completed |
| target_date | `date` | nullable |
| notes | `text` | nullable |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'status'])`
- `$table->index(['organization_id', 'criticality'])`
- `$table->index('current_employee_id')`

### succession_plan_candidates

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| succession_plan_id | `foreignId` | constrained, cascadeOnDelete |
| employee_id | `foreignId` | constrained, cascadeOnDelete |
| readiness | `string(30)` | default('development_needed') — ready_now, ready_1_year, ready_2_years, development_needed |
| rank | `unsignedTinyInteger` | default(1) |
| strengths | `text` | nullable |
| development_areas | `text` | nullable |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['succession_plan_id', 'employee_id'])`
- `$table->index(['succession_plan_id', 'readiness'])`
- `$table->index('employee_id')`

### succession_pool_activities

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| candidate_id | `foreignId` | constrained('succession_candidates'), cascadeOnDelete |
| employee_id | `foreignId` | constrained('employees'), cascadeOnDelete |
| activity_type | `string(50)` |  |
| title | `string` |  |
| description | `text` | nullable |
| target_date | `date` | nullable |
| completed_date | `date` | nullable |
| status | `enum(['planned', 'in_progress', 'completed', 'cancelled'])` | default('planned') |
| outcome | `text` | nullable |
| assigned_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['candidate_id', 'status'], 'succ_act_cand_status_idx')`
- `$table->index(['employee_id', 'status'], 'succ_act_emp_status_idx')`

### time_sheets

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string(36)` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| employee_id | `foreignId` | constrained('employees'), cascadeOnDelete |
| period_start | `date` |  |
| period_end | `date` |  |
| status | `enum(['draft', 'submitted', 'approved', 'rejected', 'transferred_to_payroll', ])` | default('draft') |
| approved_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| approved_at | `timestamp` | nullable |
| total_regular_hours | `decimal(8, 2)` | default(0) |
| total_overtime_hours | `decimal(8, 2)` | default(0) |
| total_absence_hours | `decimal(8, 2)` | default(0) |
| created_by | `foreignId` | constrained('users') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['employee_id', 'period_start', 'period_end'], 'ts_emp_period_unique')`
- `$table->index(['organization_id', 'status'], 'ts_org_status_idx')`

### time_wage_types

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| code | `string(10)` | OT15=1.5x overtime, NT=night diff, WE=weekend |
| name | `string(100)` |  |
| wage_category | `enum(['overtime', 'night_differential', 'weekend', 'holiday', 'absence_deduction', 'other', ])` | default('other') |
| rate_multiplier | `decimal(5, 4)` | default(1.0) |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'code'], 'twt_org_code_unique')`

### time_evaluation_results

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| time_sheet_id | `foreignId` | constrained('time_sheets'), cascadeOnDelete |
| employee_id | `foreignId` | constrained('employees'), cascadeOnDelete |
| evaluation_date | `date` |  |
| wage_type_id | `foreignId` | constrained('time_wage_types'), cascadeOnDelete |
| hours | `decimal(5, 2)` |  |
| amount | `decimal(15, 4)` | default(0) |
| currency_code | `string(3)` | default('SAR') |
| transferred_to_payroll | `boolean` | default(false) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['time_sheet_id'], 'ter_sheet_idx')`
- `$table->index(['employee_id', 'evaluation_date'], 'ter_emp_date_idx')`

### training_providers

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| name | `string` |  |
| contact_name | `string` | nullable |
| email | `string` | nullable |
| phone | `string` | nullable |
| website | `string` | nullable |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('organization_id')`

### training_courses

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| provider_id | `foreignId` | nullable, constrained('training_providers'), nullOnDelete |
| code | `string` |  |
| name | `string` |  |
| description | `text` | nullable |
| category | `enum(['technical', 'soft_skills', 'compliance', 'safety', 'leadership', 'onboarding', 'other', ])` | default('other') |
| delivery_type | `enum(['in_person', 'online', 'blended', 'self_paced', ])` | default('in_person') |
| duration_hours | `decimal(5, 1)` | default(1) |
| max_participants | `unsignedInteger` | nullable |
| is_mandatory | `boolean` | default(false) |
| validity_months | `unsignedInteger` | nullable, comment('Months before recertification needed') |
| cost_per_participant | `decimal(10, 2)` | nullable |
| currency_code | `string(3)` | default('SAR') |
| is_active | `boolean` | default(true) |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| deleted_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'code'])`
- `$table->index('organization_id')`

### training_needs

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| employee_id | `foreignId` | nullable, constrained('employees'), nullOnDelete, comment('null = department-wide') |
| department_id | `foreignId` | nullable, constrained('departments'), nullOnDelete |
| course_id | `foreignId` | nullable, constrained('training_courses'), nullOnDelete |
| title | `string` |  |
| description | `text` | nullable |
| priority | `enum(['low', 'medium', 'high'])` | default('medium') |
| status | `enum(['identified', 'planned', 'fulfilled', 'cancelled'])` | default('identified') |
| identified_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| target_date | `date` | nullable |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('organization_id')`
- `$table->index(['organization_id', 'status'])`

### training_sessions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| course_id | `foreignId` | constrained('training_courses'), cascadeOnDelete |
| session_number | `string` |  |
| trainer_name | `string` | nullable |
| location | `string` | nullable |
| meeting_link | `string` | nullable |
| start_date | `dateTime` |  |
| end_date | `dateTime` |  |
| max_participants | `unsignedInteger` | nullable |
| enrolled_count | `unsignedInteger` | default(0) |
| status | `enum(['scheduled', 'in_progress', 'completed', 'cancelled', ])` | default('scheduled') |
| notes | `text` | nullable |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'status'])`
- `$table->index(['course_id', 'status'])`

### training_enrollments

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| session_id | `foreignId` | constrained('training_sessions'), cascadeOnDelete |
| employee_id | `foreignId` | constrained('employees'), cascadeOnDelete |
| status | `enum(['enrolled', 'attended', 'completed', 'failed', 'cancelled', 'no_show', ])` | default('enrolled') |
| enrolled_at | `timestamp` |  |
| completion_date | `date` | nullable |
| score | `decimal(5, 2)` | nullable, comment('Percentage') |
| feedback | `text` | nullable |
| enrolled_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['session_id', 'employee_id'])`
- `$table->index(['employee_id', 'status'])`
- `$table->index('organization_id')`

### training_certifications

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| employee_id | `foreignId` | constrained('employees'), cascadeOnDelete |
| course_id | `foreignId` | constrained('training_courses'), cascadeOnDelete |
| enrollment_id | `foreignId` | nullable, constrained('training_enrollments'), nullOnDelete |
| certificate_number | `string` | nullable |
| issued_date | `date` |  |
| expiry_date | `date` | nullable |
| is_active | `boolean` | default(true) |
| issued_by | `string` | nullable |
| notes | `text` | nullable |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['employee_id', 'course_id'])`
- `$table->index('organization_id')`

### travel_expense_types

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| code | `string(20)` |  |
| name | `string(100)` |  |
| category | `enum(['accommodation', 'transport', 'meals', 'entertainment', 'other'])` |  |
| daily_limit | `decimal(15, 4)` | nullable |
| gl_account_code | `string(20)` | nullable |
| requires_receipt | `boolean` | default(true) |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'code'])`

### travel_requests

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string(36)` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| employee_id | `foreignId` | constrained('employees'), cascadeOnDelete |
| request_number | `string(30)` |  |
| purpose | `string(500)` |  |
| departure_date | `date` |  |
| return_date | `date` |  |
| destination_country | `string(3)` |  |
| destination_city | `string(100)` | nullable |
| travel_type | `enum(['domestic', 'international'])` | default('domestic') |
| estimated_cost | `decimal(15, 4)` | default(0) |
| advance_requested | `decimal(15, 4)` | default(0) |
| advance_approved | `decimal(15, 4)` | default(0) |
| status | `enum(['draft', 'submitted', 'approved', 'rejected', 'completed', 'cancelled', ])` | default('draft') |
| approved_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| approved_at | `timestamp` | nullable |
| rejection_reason | `text` | nullable |
| created_by | `foreignId` | constrained('users') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'request_number'], 'tr_org_number_unique')`
- `$table->index(['employee_id', 'status'], 'tr_emp_status_idx')`

### travel_expense_claims

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string(36)` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| travel_request_id | `foreignId` | nullable, constrained('travel_requests'), nullOnDelete |
| employee_id | `foreignId` | constrained('employees'), cascadeOnDelete |
| claim_number | `string(30)` |  |
| claim_date | `date` |  |
| total_claimed | `decimal(15, 4)` | default(0) |
| advance_paid | `decimal(15, 4)` | default(0) |
| amount_reimbursable | `decimal(15, 4)` | default(0) |
| amount_deductible | `decimal(15, 4)` | default(0) |
| status | `enum(['draft', 'submitted', 'approved', 'rejected', 'paid', ])` | default('draft') |
| approved_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_by | `foreignId` | constrained('users') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'claim_number'], 'tec_org_number_unique')`
- `$table->index(['employee_id', 'status'], 'tec_emp_status_idx')`

### travel_expense_lines

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| claim_id | `foreignId` | constrained('travel_expense_claims'), cascadeOnDelete |
| expense_date | `date` |  |
| expense_category | `enum(['flight', 'hotel', 'meal', 'transport', 'per_diem', 'mileage', 'visa', 'other', ])` | default('other') |
| description | `string(255)` | nullable |
| amount | `decimal(15, 4)` |  |
| mileage_km | `decimal(10, 2)` | nullable |
| currency_code | `string(3)` | default('SAR') |
| exchange_rate | `decimal(15, 6)` | default(1) |
| amount_in_base_currency | `decimal(15, 4)` | default(0) |
| receipt_reference | `string(100)` | nullable |
| receipt_attached | `boolean` | default(false) |
| policy_limit | `decimal(15, 4)` | nullable |
| within_policy | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['claim_id'], 'tel_claim_idx')`

### travel_expense_reports

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| report_number | `string(30)` | unique |
| travel_request_id | `foreignId` | nullable, constrained('travel_requests'), nullOnDelete |
| employee_id | `foreignId` | constrained('employees'), restrictOnDelete |
| report_date | `date` |  |
| total_amount | `decimal(15, 4)` | default(0) |
| currency_code | `string(3)` | default('SAR') |
| status | `enum(['draft', 'submitted', 'approved', 'rejected', 'posted'])` | default('draft') |
| notes | `text` | nullable |
| approved_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| approved_at | `timestamp` | nullable |
| journal_entry_id | `string` | nullable |
| created_by | `foreignId` | constrained('users'), restrictOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'employee_id'])`
- `$table->index(['organization_id', 'status'])`

### travel_expense_report_lines

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| expense_report_id | `foreignId` | constrained('travel_expense_reports'), cascadeOnDelete |
| expense_type_id | `foreignId` | constrained('travel_expense_types'), restrictOnDelete |
| expense_date | `date` |  |
| description | `text` |  |
| amount | `decimal(15, 4)` |  |
| currency_code | `string(3)` | default('SAR') |
| amount_in_local | `decimal(15, 4)` | nullable |
| receipt_attached | `boolean` | default(false) |
| receipt_path | `string` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

### work_schedules

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| name | `string(100)` |  |
| code | `string(20)` | nullable |
| start_time | `time` |  |
| end_time | `time` |  |
| break_duration | `decimal(4, 2)` | default(0) — in hours |
| working_hours | `decimal(4, 2)` | default(8) |
| work_days | `json` | nullable — [1,2,3,4,5] for Mon-Fri |
| is_flexible | `boolean` | default(false) |
| grace_period_minutes | `unsignedSmallInteger` | default(0) |
| is_default | `boolean` | default(false) |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'code'])`

### attendances

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| employee_id | `foreignId` | constrained, cascadeOnDelete |
| attendance_date | `date` |  |
| work_schedule_id | `foreignId` | nullable, constrained, nullOnDelete |
| check_in | `datetime` | nullable |
| check_out | `datetime` | nullable |
| break_start | `datetime` | nullable |
| break_end | `datetime` | nullable |
| working_hours | `decimal(5, 2)` | default(0) |
| overtime_hours | `decimal(5, 2)` | default(0) |
| break_hours | `decimal(5, 2)` | default(0) |
| late_minutes | `integer` | default(0) |
| early_leaving_minutes | `integer` | default(0) |
| status | `enum(['present', 'absent', 'half_day', 'on_leave', 'holiday', 'weekend', 'work_from_home', 'on_duty', ])` | default('present') |
| source | `enum(['manual', 'biometric', 'geo_fence', 'import'])` | default('manual') |
| device_id | `string(100)` | nullable |
| check_in_latitude | `decimal(10, 8)` | nullable |
| check_in_longitude | `decimal(11, 8)` | nullable |
| check_out_latitude | `decimal(10, 8)` | nullable |
| check_out_longitude | `decimal(11, 8)` | nullable |
| is_regularized | `boolean` | default(false) |
| regularization_reason | `string(500)` | nullable |
| approved_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| approved_at | `datetime` | nullable |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['employee_id', 'attendance_date'])`
- `$table->index(['organization_id', 'attendance_date'])`
- `$table->index(['employee_id', 'status'])`

## 0180_accounting_5.php

### cost_centers

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| parent_id | `unsignedBigInteger` | nullable |
| code | `string(50)` |  |
| name | `string(255)` |  |
| description | `text` | nullable |
| manager_id | `unsignedBigInteger` | nullable |
| department_id | `unsignedBigInteger` | nullable |
| status | `enum(['active', 'inactive'])` | default('active') |
| valid_from | `date` | nullable |
| valid_to | `date` | nullable |
| gl_account_id | `unsignedBigInteger` | nullable |
| is_statistical | `boolean` | default(false) |
| deleted_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'code'])`
- `$table->index(['organization_id', 'status'])`
- `$table->index('organization_id')`

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade')`
- `$table->foreign('parent_id')->references('id')->on('cost_centers')->onDelete('set null')`
- `$table->foreign('manager_id')->references('id')->on('employees')->onDelete('set null')`
- `$table->foreign('department_id')->references('id')->on('departments')->onDelete('set null')`
- `$table->foreign('gl_account_id')->references('id')->on('chart_of_accounts')->onDelete('set null')`

### activity_rates

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| activity_type_id | `foreignId` | constrained('activity_types'), cascadeOnDelete |
| cost_center_id | `foreignId` | constrained('cost_centers'), cascadeOnDelete |
| fiscal_year_id | `foreignId` | constrained('fiscal_years'), cascadeOnDelete |
| period | `integer` | default(1) — 1-12 |
| planned_rate | `decimal(15, 4)` | default(0) |
| actual_rate | `decimal(15, 4)` | default(0) |
| currency_code | `string(3)` | default('SAR') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| is_confirmed | `boolean` | default(false) |
| confirmed_at | `timestamp` | nullable |
| confirmed_by | `foreignId` | nullable, constrained('users'), nullOnDelete |

Indexes:

- `$table->unique(['activity_type_id', 'cost_center_id', 'fiscal_year_id', 'period'], 'ar_unique_rate')`

### assessment_postings

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string(36)` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| assessment_cycle_id | `foreignId` | constrained('assessment_cycles', 'id', 'co_asmt_post_cycle_fk'), cascadeOnDelete |
| fiscal_year | `unsignedSmallInteger` |  |
| period | `tinyInteger` | unsigned |
| sender_cost_center_id | `foreignId` | nullable, constrained('cost_centers', 'id', 'co_asmt_post_snd_cc_fk'), nullOnDelete |
| receiver_cost_center_id | `foreignId` | nullable, constrained('cost_centers', 'id', 'co_asmt_post_rcv_cc_fk'), nullOnDelete |
| cost_element_id | `foreignId` | nullable, constrained('cost_elements', 'id', 'co_asmt_post_ce_fk'), nullOnDelete |
| amount | `decimal(18, 4)` |  |
| currency | `char(3)` | default('SAR') |
| reversal_id | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'fiscal_year', 'period'], 'co_asmt_post_org_fy_period_idx')`
- `$table->index(['assessment_cycle_id'], 'co_asmt_post_cycle_idx')`

Foreign keys:

- `$table->foreign('reversal_id', 'co_asmt_post_reversal_fk')->references('id')->on('assessment_postings')->nullOnDelete()`

### distribution_postings

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string(36)` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| distribution_cycle_id | `foreignId` | constrained('distribution_cycles', 'id', 'co_dist_post_cycle_fk'), cascadeOnDelete |
| fiscal_year | `unsignedSmallInteger` |  |
| period | `tinyInteger` | unsigned |
| sender_cost_center_id | `foreignId` | constrained('cost_centers', 'id', 'co_dist_post_snd_cc_fk'), restrictOnDelete |
| receiver_cost_center_id | `foreignId` | nullable, constrained('cost_centers', 'id', 'co_dist_post_rcv_cc_fk'), nullOnDelete |
| cost_element_id | `foreignId` | constrained('cost_elements', 'id', 'co_dist_post_ce_fk'), restrictOnDelete |
| amount | `decimal(18, 4)` |  |
| currency | `char(3)` | default('SAR') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'fiscal_year', 'period'], 'co_dist_post_org_fy_period_idx')`
- `$table->index(['distribution_cycle_id'], 'co_dist_post_cycle_idx')`

### distribution_segments

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string(36)` | unique |
| distribution_cycle_id | `foreignId` | constrained('distribution_cycles', 'id', 'co_dist_seg_cycle_fk'), cascadeOnDelete |
| sender_cost_center_id | `foreignId` | constrained('cost_centers', 'id', 'co_dist_seg_snd_cc_fk'), restrictOnDelete |
| cost_element_ids | `json` |  |
| tracing_factor | `enum(['fixed_percentages', 'statistical_key_figure', 'posted_amounts'])` | default('fixed_percentages') |
| skf_id | `foreignId` | nullable, constrained('statistical_key_figures', 'id', 'co_dist_seg_skf_fk'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['distribution_cycle_id'], 'co_dist_seg_cycle_idx')`

### cost_allocations

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| fiscal_year_id | `unsignedBigInteger` | nullable |
| period_start | `date` |  |
| period_end | `date` |  |
| from_cost_center_id | `unsignedBigInteger` |  |
| to_cost_center_id | `unsignedBigInteger` |  |
| allocation_method | `enum(['fixed', 'percentage', 'activity'])` | default('percentage') |
| allocation_percent | `decimal(5, 2)` | nullable |
| allocation_amount | `decimal(15, 4)` | nullable |
| description | `string(500)` | nullable |
| journal_entry_id | `unsignedBigInteger` | nullable |
| status | `enum(['draft', 'posted'])` | default('draft') |
| created_by | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'status'])`
- `$table->index(['organization_id', 'period_start', 'period_end'])`

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade')`
- `$table->foreign('fiscal_year_id')->references('id')->on('fiscal_years')->onDelete('set null')`
- `$table->foreign('from_cost_center_id')->references('id')->on('cost_centers')->onDelete('restrict')`
- `$table->foreign('to_cost_center_id')->references('id')->on('cost_centers')->onDelete('restrict')`
- `$table->foreign('journal_entry_id')->references('id')->on('journal_entries')->onDelete('set null')`
- `$table->foreign('created_by')->references('id')->on('users')->onDelete('set null')`

### cost_center_budgets

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string(36)` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| cost_center_id | `foreignId` | constrained('cost_centers', 'id', 'cc_budget_cc_fk'), cascadeOnDelete |
| fiscal_year | `unsignedSmallInteger` |  |
| budget_version | `string(20)` | default('0') |
| total_budget | `decimal(18, 4)` | default(0) |
| currency | `char(3)` | default('SAR') |
| status | `enum(['draft', 'approved', 'active'])` | default('draft') |
| approved_by | `foreignId` | nullable, constrained('users', 'id', 'cc_budget_approved_by_fk'), nullOnDelete |
| approved_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'cost_center_id', 'fiscal_year', 'budget_version'], 'cc_budget_unique')`
- `$table->index(['organization_id', 'fiscal_year', 'status'], 'cc_budget_org_fy_status_idx')`

### cost_center_budget_lines

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| cost_center_budget_id | `foreignId` | constrained('cost_center_budgets', 'id', 'cc_bdgt_line_budget_fk'), cascadeOnDelete |
| period | `tinyInteger` | unsigned — 1-12 |
| cost_element_id | `foreignId` | nullable, constrained('cost_elements', 'id', 'cc_bdgt_line_ce_fk'), nullOnDelete |
| budgeted_amount | `decimal(18, 4)` | default(0) |
| committed_amount | `decimal(18, 4)` | default(0) |
| actual_amount | `decimal(18, 4)` | default(0) |
| available_amount | `decimal(18, 4)` | storedAs('budgeted_amount - committed_amount - actual_amount') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['cost_center_budget_id', 'period'], 'cc_bdgt_line_budget_period_idx')`

### cost_center_budget_supplements

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string(36)` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| cost_center_budget_id | `foreignId` | constrained('cost_center_budgets', 'id', 'cc_bdgt_supp_budget_fk'), cascadeOnDelete |
| supplement_number | `string(50)` | unique |
| requested_amount | `decimal(18, 4)` |  |
| approved_amount | `decimal(18, 4)` | nullable |
| reason | `text` |  |
| status | `enum(['pending', 'approved', 'rejected'])` | default('pending') |
| requested_by | `foreignId` | constrained('users', 'id', 'cc_bdgt_supp_req_by_fk'), restrictOnDelete |
| reviewed_by | `foreignId` | nullable, constrained('users', 'id', 'cc_bdgt_supp_rev_by_fk'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'status'], 'cc_bdgt_supp_org_status_idx')`
- `$table->index(['cost_center_budget_id'], 'cc_bdgt_supp_budget_idx')`

### costing_sheet_rows

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| costing_sheet_id | `unsignedBigInteger` |  |
| row_type | `string(20)` |  |
| description | `string` |  |
| sort_order | `integer` | default(0) |
| base_cost_element_id | `unsignedBigInteger` | nullable |
| overhead_key_id | `unsignedBigInteger` | nullable |
| credit_cost_center_id | `unsignedBigInteger` | nullable |
| credit_cost_element_id | `unsignedBigInteger` | nullable |
| from_row | `integer` | nullable |
| to_row | `integer` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('costing_sheet_id', 'csr_cs_fk')->references('id')->on('costing_sheets')->onDelete('cascade')`
- `$table->foreign('base_cost_element_id', 'csr_base_ce_fk')->references('id')->on('cost_elements')->onDelete('set null')`
- `$table->foreign('credit_cost_center_id', 'csr_credit_cc_fk')->references('id')->on('cost_centers')->onDelete('set null')`
- `$table->foreign('credit_cost_element_id', 'csr_credit_ce_fk')->references('id')->on('cost_elements')->onDelete('set null')`
- `$table->foreign('overhead_key_id', 'csr_ok_fk')->references('id')->on('overhead_keys')->onDelete('set null')`

### costing_sheet_run_results

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| costing_sheet_run_id | `unsignedBigInteger` |  |
| costing_sheet_row_id | `unsignedBigInteger` |  |
| base_amount | `decimal(18, 4)` | default(0) |
| overhead_rate | `decimal(10, 6)` | default(0) |
| overhead_amount | `decimal(18, 4)` | default(0) |
| credit_posted | `boolean` | default(false) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('costing_sheet_run_id', 'csrr_run_fk')->references('id')->on('costing_sheet_runs')->onDelete('cascade')`
- `$table->foreign('costing_sheet_row_id', 'csrr_row_fk')->references('id')->on('costing_sheet_rows')->onDelete('cascade')`

### internal_orders

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string(36)` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| order_number | `string(30)` |  |
| description | `string(255)` |  |
| order_type | `enum(['overhead', 'investment', 'accrual', 'statistical'])` | default('overhead') |
| cost_center_id | `foreignId` | nullable, constrained('cost_centers'), nullOnDelete |
| responsible_user_id | `foreignId` | nullable, constrained('users'), nullOnDelete |
| start_date | `date` | nullable |
| end_date | `date` | nullable |
| budget_amount | `decimal(15, 4)` | default(0) |
| committed_amount | `decimal(15, 4)` | default(0) |
| actual_amount | `decimal(15, 4)` | default(0) |
| status | `enum(['created', 'released', 'technically_completed', 'closed'])` | default('created') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'order_number'], 'io_org_number_unique')`

### internal_order_settlements

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| internal_order_id | `foreignId` | constrained('internal_orders'), cascadeOnDelete |
| receiver_type | `enum(['cost_center', 'gl_account', 'project_wbs', 'profit_center'])` |  |
| receiver_id | `unsignedBigInteger` |  |
| settlement_percentage | `decimal(5, 2)` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['internal_order_id'], 'ios_order_idx')`

### overhead_key_rates

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| overhead_key_id | `unsignedBigInteger` |  |
| validity_from | `date` |  |
| validity_to | `date` | nullable |
| overhead_rate | `decimal(10, 6)` |  |
| currency_code | `string(3)` |  |
| cost_center_id | `unsignedBigInteger` | nullable |
| activity_type_id | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['overhead_key_id', 'validity_from'], 'okr_key_validity_idx')`

Foreign keys:

- `$table->foreign('overhead_key_id', 'okr_ok_fk')->references('id')->on('overhead_keys')->onDelete('cascade')`
- `$table->foreign('cost_center_id', 'okr_cc_fk')->references('id')->on('cost_centers')->onDelete('set null')`
- `$table->foreign('activity_type_id', 'okr_at_fk')->references('id')->on('activity_types')->onDelete('set null')`

### profit_centers

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| parent_id | `unsignedBigInteger` | nullable |
| code | `string(50)` |  |
| name | `string(255)` |  |
| description | `text` | nullable |
| manager_id | `unsignedBigInteger` | nullable |
| status | `enum(['active', 'inactive'])` | default('active') |
| valid_from | `date` | nullable |
| valid_to | `date` | nullable |
| gl_account_id | `unsignedBigInteger` | nullable |
| deleted_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'code'])`
- `$table->index(['organization_id', 'status'])`
- `$table->index('organization_id')`

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade')`
- `$table->foreign('parent_id')->references('id')->on('profit_centers')->onDelete('set null')`
- `$table->foreign('manager_id')->references('id')->on('employees')->onDelete('set null')`
- `$table->foreign('gl_account_id')->references('id')->on('chart_of_accounts')->onDelete('set null')`

### assessment_cycle_segments

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string(36)` | unique |
| assessment_cycle_id | `foreignId` | constrained('assessment_cycles', 'id', 'co_asmt_seg_cycle_fk'), cascadeOnDelete |
| segment_number | `unsignedSmallInteger` | default(1) |
| sender_cost_center_id | `foreignId` | nullable, constrained('cost_centers', 'id', 'co_asmt_seg_snd_cc_fk'), nullOnDelete |
| sender_profit_center_id | `foreignId` | nullable, constrained('profit_centers', 'id', 'co_asmt_seg_snd_pc_fk'), nullOnDelete |
| sender_cost_element_id | `foreignId` | nullable, constrained('cost_elements', 'id', 'co_asmt_seg_snd_ce_fk'), nullOnDelete |
| tracing_factor | `enum(['fixed_percentages', 'statistical_key_figure', 'posted_amounts'])` | default('fixed_percentages') |
| skf_id | `foreignId` | nullable, constrained('statistical_key_figures', 'id', 'co_asmt_seg_skf_fk'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['assessment_cycle_id'], 'co_asmt_seg_cycle_idx')`

### assessment_cycle_receivers

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| assessment_cycle_segment_id | `foreignId` | constrained('assessment_cycle_segments', 'id', 'co_asmt_rcv_seg_fk'), cascadeOnDelete |
| receiver_cost_center_id | `foreignId` | nullable, constrained('cost_centers', 'id', 'co_asmt_rcv_cc_fk'), nullOnDelete |
| receiver_profit_center_id | `foreignId` | nullable, constrained('profit_centers', 'id', 'co_asmt_rcv_pc_fk'), nullOnDelete |
| receiver_order_id | `foreignId` | nullable, constrained('internal_orders', 'id', 'co_asmt_rcv_io_fk'), nullOnDelete |
| fixed_percentage | `decimal(8, 4)` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['assessment_cycle_segment_id'], 'co_asmt_rcv_seg_idx')`

### distribution_segment_receivers

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| distribution_segment_id | `foreignId` | constrained('distribution_segments', 'id', 'co_dist_rcv_seg_fk'), cascadeOnDelete |
| receiver_cost_center_id | `foreignId` | nullable, constrained('cost_centers', 'id', 'co_dist_rcv_cc_fk'), nullOnDelete |
| receiver_profit_center_id | `foreignId` | nullable, constrained('profit_centers', 'id', 'co_dist_rcv_pc_fk'), nullOnDelete |
| fixed_percentage | `decimal(8, 4)` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['distribution_segment_id'], 'co_dist_rcv_seg_idx')`

### copa_line_items

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| fiscal_year_id | `foreignId` | constrained('fiscal_years'), cascadeOnDelete |
| period | `integer` |  |
| posting_date | `date` |  |
| source_document_type | `string(30)` | invoice, credit_note, settlement |
| source_document_id | `unsignedBigInteger` | nullable |
| profit_center_id | `foreignId` | nullable, constrained('profit_centers'), nullOnDelete |
| cost_center_id | `foreignId` | nullable, constrained('cost_centers'), nullOnDelete |
| product_id | `unsignedBigInteger` | nullable |
| contact_id | `unsignedBigInteger` | nullable |
| revenue | `decimal(15, 4)` | default(0) |
| cogs | `decimal(15, 4)` | default(0) |
| gross_profit | `decimal(15, 4)` | default(0) |
| overhead_allocated | `decimal(15, 4)` | default(0) |
| net_profit | `decimal(15, 4)` | default(0) |
| currency_code | `string(3)` | default('SAR') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'fiscal_year_id', 'period'], 'copa_li_org_fy_period_idx')`
- `$table->index(['organization_id', 'posting_date'], 'copa_li_org_date_idx')`

### copa_planned_line_items

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| plan_version_id | `foreignId` | constrained('copa_plan_versions'), cascadeOnDelete |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| period | `integer` |  |
| profit_center_id | `foreignId` | nullable, constrained('profit_centers'), nullOnDelete |
| product_id | `unsignedBigInteger` | nullable |
| contact_id | `unsignedBigInteger` | nullable |
| planned_revenue | `decimal(15, 4)` | default(0) |
| planned_cogs | `decimal(15, 4)` | default(0) |
| planned_gross_profit | `decimal(15, 4)` | default(0) |
| planned_overhead | `decimal(15, 4)` | default(0) |
| planned_net_profit | `decimal(15, 4)` | default(0) |
| currency_code | `string(3)` | default('SAR') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['plan_version_id', 'period'], 'cpli_version_period_idx')`
- `$table->index(['organization_id', 'profit_center_id'], 'cpli_org_pc_idx')`

### cost_center_assignments

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `unsignedBigInteger` |  |
| assignable_type | `string(191)` |  |
| assignable_id | `unsignedBigInteger` |  |
| cost_center_id | `unsignedBigInteger` |  |
| profit_center_id | `unsignedBigInteger` | nullable |
| split_percent | `decimal(5, 2)` | default(100.00) |
| effective_from | `date` |  |
| effective_to | `date` | nullable |
| created_by | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['assignable_type', 'assignable_id'])`
- `$table->index('cost_center_id')`
- `$table->index('organization_id')`

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade')`
- `$table->foreign('cost_center_id')->references('id')->on('cost_centers')->onDelete('cascade')`
- `$table->foreign('profit_center_id')->references('id')->on('profit_centers')->onDelete('set null')`
- `$table->foreign('created_by')->references('id')->on('users')->onDelete('set null')`

### journal_entry_split_items

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| journal_entry_id | `foreignId` | constrained('journal_entries'), cascadeOnDelete |
| original_line_id | `unsignedBigInteger` | nullable |
| profit_center_id | `foreignId` | nullable, constrained('profit_centers'), nullOnDelete |
| cost_center_id | `foreignId` | nullable, constrained('cost_centers'), nullOnDelete |
| debit_amount | `decimal(15, 4)` | default(0) |
| credit_amount | `decimal(15, 4)` | default(0) |
| currency_code | `string(3)` | default('SAR') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| segment_id | `string(50)` | nullable |
| split_method | `string(30)` | default('profit_center') |

Indexes:

- `$table->index(['journal_entry_id'], 'jesi_je_idx')`
- `$table->index(['profit_center_id'], 'jesi_pc_idx')`

### profit_center_plans

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| profit_center_id | `foreignId` | constrained('profit_centers'), cascadeOnDelete |
| fiscal_year | `unsignedSmallInteger` |  |
| period | `unsignedTinyInteger` | 1–12 |
| plan_revenue | `decimal(15, 4)` | default(0) |
| plan_cost | `decimal(15, 4)` | default(0) |
| plan_profit | `decimal(15, 4)` | default(0) |
| currency_code | `char(3)` | default('SAR') |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'profit_center_id', 'fiscal_year', 'period'], 'pcp_org_pc_year_period_unique')`

### recurring_journal_templates

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| name | `string(255)` |  |
| description | `text` | nullable |
| frequency | `enum(['daily', 'weekly', 'monthly', 'quarterly', 'annually'])` |  |
| interval | `unsignedTinyInteger` | default(1) |
| start_date | `date` |  |
| end_date | `date` | nullable |
| next_run_date | `date` |  |
| last_run_date | `date` | nullable |
| run_count | `unsignedInteger` | default(0) |
| max_runs | `unsignedInteger` | nullable |
| debit_account_id | `unsignedBigInteger` |  |
| credit_account_id | `unsignedBigInteger` |  |
| amount | `decimal(15, 4)` |  |
| currency_code | `char(3)` | default('SAR') |
| narration | `text` | nullable |
| cost_center_id | `unsignedBigInteger` | nullable |
| profit_center_id | `unsignedBigInteger` | nullable |
| is_active | `boolean` | default(true) |
| created_by | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'is_active', 'next_run_date'], 'recurring_journal_templates_org_active_next_run_idx')`

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade')`
- `$table->foreign('debit_account_id')->references('id')->on('chart_of_accounts')`
- `$table->foreign('credit_account_id')->references('id')->on('chart_of_accounts')`
- `$table->foreign('cost_center_id')->references('id')->on('cost_centers')->nullOnDelete()`
- `$table->foreign('profit_center_id')->references('id')->on('profit_centers')->nullOnDelete()`
- `$table->foreign('created_by')->references('id')->on('users')->nullOnDelete()`

### budget_lines

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| budget_id | `foreignId` | constrained('budgets'), cascadeOnDelete |
| account_id | `foreignId` | nullable, constrained('chart_of_accounts'), nullOnDelete |
| cost_center_id | `foreignId` | nullable, constrained('cost_centers'), nullOnDelete |
| department_id | `foreignId` | nullable, constrained('departments'), nullOnDelete |
| name | `string` |  |
| q1_amount | `decimal(15, 2)` | default(0) |
| q2_amount | `decimal(15, 2)` | default(0) |
| q3_amount | `decimal(15, 2)` | default(0) |
| q4_amount | `decimal(15, 2)` | default(0) |
| total_amount | `decimal(15, 2)` | default(0) |
| committed_amount | `decimal(15, 2)` | default(0) |
| actual_amount | `decimal(15, 2)` | default(0) |
| notes | `string` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('budget_id')`

### budget_commitments

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| budget_line_id | `foreignId` | constrained('budget_lines'), cascadeOnDelete |
| source_type | `enum(['purchase_order', 'expense_report', 'journal_entry'])` | default('purchase_order') |
| source_id | `unsignedBigInteger` |  |
| committed_amount | `decimal(15, 2)` |  |
| status | `enum(['open', 'partially_used', 'used', 'cancelled'])` | default('open') |
| committed_at | `timestamp` |  |
| released_at | `timestamp` | nullable |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('organization_id')`
- `$table->index('budget_line_id')`
- `$table->index(['source_type', 'source_id'])`

### budget_revision_lines

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| budget_revision_id | `foreignId` | constrained('budget_revisions'), cascadeOnDelete |
| budget_line_id | `foreignId` | constrained('budget_lines'), cascadeOnDelete |
| field_changed | `string` |  |
| old_value | `decimal(15, 2)` |  |
| new_value | `decimal(15, 2)` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

### territory_assignments

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| territory_id | `foreignId` | constrained('territories'), cascadeOnDelete |
| employee_id | `foreignId` | constrained('employees'), cascadeOnDelete |
| role | `enum(['owner', 'backup', 'viewer'])` | default('owner') |
| effective_from | `date` |  |
| effective_to | `date` | nullable |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('territory_id')`
- `$table->index('employee_id')`

### expense_budgets

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| category_id | `foreignId` | nullable, constrained('expense_categories'), nullOnDelete |
| department_id | `foreignId` | nullable, constrained('departments'), nullOnDelete |
| year | `unsignedSmallInteger` |  |
| month | `unsignedTinyInteger` | nullable — NULL for annual budget |
| budget_amount | `decimal(15, 2)` |  |
| spent_amount | `decimal(15, 2)` | default(0) |
| committed_amount | `decimal(15, 2)` | default(0) — Pending approvals |
| alert_at_80 | `boolean` | default(true) |
| alert_at_100 | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'category_id', 'department_id', 'year', 'month'], 'expense_budgets_org_cat_dept_yr_mo_unique')`

### expense_reports

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| employee_id | `foreignId` | constrained('employees'), cascadeOnDelete |
| report_number | `string(30)` |  |
| title | `string` |  |
| description | `text` | nullable |
| period_start | `date` |  |
| period_end | `date` |  |
| total_amount | `decimal(15, 2)` | default(0) |
| approved_amount | `decimal(15, 2)` | default(0) |
| reimbursed_amount | `decimal(15, 2)` | default(0) |
| status | `string(20)` | default('draft') — draft, submitted, approved, rejected, paid |
| approved_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| approved_at | `timestamp` | nullable |
| paid_at | `timestamp` | nullable |
| rejection_reason | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'status'])`
- `$table->index(['employee_id', 'status'])`

### org_units

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| name | `string` |  |
| short_name | `string(20)` | nullable |
| unit_type | `enum(['company', 'division', 'department', 'team', 'cost_center_group'])` |  |
| parent_id | `unsignedBigInteger` | nullable |
| manager_id | `unsignedBigInteger` | nullable |
| cost_center_id | `unsignedBigInteger` | nullable |
| valid_from | `date` |  |
| valid_to | `date` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |
| org_unit_code | `string(20)` | nullable |
| org_unit_type | `string(50)` | nullable |
| is_active | `boolean` | default(true) |
| head_count_plan | `unsignedSmallInteger` | default(0) |
| manager_position_id | `unsignedBigInteger` | nullable |
| created_by | `unsignedBigInteger` | nullable |

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade')`
- `$table->foreign('parent_id', 'org_unit_parent_fk')->references('id')->on('org_units')->onDelete('set null')`
- `$table->foreign('manager_id', 'org_unit_mgr_fk')->references('id')->on('employees')->onDelete('set null')`
- `$table->foreign('cost_center_id', 'org_unit_cc_fk')->references('id')->on('cost_centers')->onDelete('set null')`

### time_sheet_entries

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| time_sheet_id | `foreignId` | constrained('time_sheets'), cascadeOnDelete |
| entry_date | `date` |  |
| start_time | `time` | nullable |
| end_time | `time` | nullable |
| hours | `decimal(5, 2)` |  |
| entry_type | `enum(['regular', 'overtime', 'absence', 'holiday', 'training', ])` | default('regular') |
| wage_type_id | `foreignId` | nullable, constrained('time_wage_types'), nullOnDelete |
| cost_center_id | `foreignId` | nullable, constrained('cost_centers'), nullOnDelete |
| work_order_id | `unsignedBigInteger` | nullable |
| activity_code | `string(20)` | nullable |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['time_sheet_id', 'entry_date'], 'tse_sheet_date_idx')`
- `$table->index(['cost_center_id', 'entry_date'], 'tse_cc_date_idx')`

## 0190_inventory.php

### batch_classes

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| class_code | `string(30)` |  |
| class_name | `string(100)` |  |
| description | `text` | nullable |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'class_code'], 'bc_org_code_unq')`

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')`

### batch_characteristics

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| batch_class_id | `unsignedBigInteger` |  |
| characteristic_code | `string(30)` |  |
| characteristic_name | `string(100)` |  |
| data_type | `enum(['text', 'numeric', 'date', 'boolean'])` | default('text') |
| unit_of_measure | `string(20)` | nullable |
| is_required | `boolean` | default(false) |
| min_value | `decimal(18, 4)` | nullable |
| max_value | `decimal(18, 4)` | nullable |
| allowed_values | `json` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['batch_class_id', 'characteristic_code'], 'bchar_class_code_unq')`

Foreign keys:

- `$table->foreign('organization_id', 'bchar_org_fk')->references('id')->on('organizations')`
- `$table->foreign('batch_class_id', 'bchar_class_fk')->references('id')->on('batch_classes')`

### categories

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| parent_id | `foreignId` | nullable, constrained('categories'), nullOnDelete |
| name | `string(100)` |  |
| slug | `string(100)` |  |
| description | `text` | nullable |
| image_url | `string(500)` | nullable |
| level | `unsignedInteger` | default(1) |
| path | `string(255)` | nullable — e.g., "1.2.3" for hierarchy |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'slug'])`
- `$table->index(['organization_id', 'parent_id'])`

### hazmat_classifications

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string` | unique |
| organization_id | `unsignedBigInteger` |  |
| classification_system | `string(30)` | ghs/un/adr/iata |
| code | `string(20)` |  |
| name | `string` |  |
| hazard_class | `string(50)` |  |
| packing_group | `string(5)` | nullable — I/II/III |
| signal_word | `string(20)` | nullable — danger/warning |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['classification_system', 'code'], 'hc_system_code_idx')`

Foreign keys:

- `$table->foreign('organization_id', 'hc_org_fk')->references('id')->on('organizations')->onDelete('cascade')`

### hazmat_storage_classes

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string` | unique |
| organization_id | `unsignedBigInteger` |  |
| code | `string(10)` |  |
| name | `string` |  |
| description | `text` | nullable |
| max_quantity_kg | `decimal(10, 2)` | nullable |
| requires_ventilation | `boolean` | default(false) |
| requires_grounding | `boolean` | default(false) |
| fire_resistance_class | `string(10)` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id', 'hsc_org_fk')->references('id')->on('organizations')->onDelete('cascade')`

### hazmat_storage_compatibility_rules

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `unsignedBigInteger` |  |
| storage_class_a_id | `unsignedBigInteger` |  |
| storage_class_b_id | `unsignedBigInteger` |  |
| is_compatible | `boolean` |  |
| restriction_notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id', 'hscr_org_fk')->references('id')->on('organizations')->onDelete('cascade')`
- `$table->foreign('storage_class_a_id', 'hscr_sc_a_fk')->references('id')->on('hazmat_storage_classes')->onDelete('cascade')`
- `$table->foreign('storage_class_b_id', 'hscr_sc_b_fk')->references('id')->on('hazmat_storage_classes')->onDelete('cascade')`

### inventory_split_valuations

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| product_id | `unsignedBigInteger` |  |
| warehouse_id | `unsignedBigInteger` | nullable |
| valuation_type_id | `unsignedBigInteger` |  |
| quantity_on_hand | `decimal(15, 4)` | default(0) |
| quantity_reserved | `decimal(15, 4)` | default(0) |
| valuation_method | `string(30)` | default('moving_average') — moving_average \| standard |
| moving_average_price | `decimal(15, 6)` | default(0) |
| standard_price | `decimal(15, 6)` | default(0) |
| total_stock_value | `decimal(15, 2)` | default(0) |
| currency | `string(3)` | default('SAR') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'product_id', 'warehouse_id', 'valuation_type_id'], 'inv_split_val_org_prod_wh_type_unique')`
- `$table->index(['organization_id', 'product_id'])`

### inventory_valuation_categories

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `unsignedBigInteger` |  |
| product_id | `unsignedBigInteger` |  |
| category_code | `string(50)` | e.g. "ORIG" – Origin-based split |
| category_name | `string` |  |
| description | `text` | nullable |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'product_id', 'category_code'], 'inv_val_cat_org_prod_code_unique')`
- `$table->index(['organization_id', 'product_id'])`

### inventory_valuation_types

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `unsignedBigInteger` |  |
| valuation_category_id | `unsignedBigInteger` |  |
| type_code | `string(50)` | e.g. "DOM", "IMP" |
| type_name | `string` |  |
| description | `text` | nullable |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['valuation_category_id', 'type_code'])`
- `$table->index(['organization_id', 'valuation_category_id'], 'inv_val_types_org_cat_idx')`

### qr_code_configs

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| entity_type | `string(50)` | product, invoice, receipt, price_tag, shelf_label |
| name | `string` |  |
| content_type | `string(30)` | url, json, vcard, text, custom |
| content_template | `text` | Template with {{placeholders}} |
| included_fields | `json` | nullable — Which fields to include |
| size_px | `unsignedSmallInteger` | default(200) |
| foreground_color | `string(7)` | default('#000000') |
| background_color | `string(7)` | default('#FFFFFF') |
| logo_path | `string` | nullable — Center logo |
| error_correction | `string(1)` | default('M') — L, M, Q, H |
| is_default | `boolean` | default(false) |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'entity_type'])`

### storage_type_determination_rules

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), name('stdr_org_fk') |
| storage_type_id | `foreignId` | constrained('storage_types'), name('stdr_st_fk') |
| warehouse_id | `foreignId` | constrained('warehouses'), name('stdr_warehouse_fk') |
| movement_type | `enum(['goods_receipt', 'goods_issue', 'transfer', 'returns'])` | default('goods_receipt') |
| product_storage_class | `string(50)` | nullable |
| max_weight_kg | `decimal(10, 2)` | nullable |
| priority | `unsignedTinyInteger` | default(50) |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['warehouse_id', 'movement_type'], 'stdr_wh_movement_idx')`

### storage_types

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations') |
| warehouse_id | `foreignId` | constrained('warehouses'), name('st_warehouse_fk') |
| storage_type_code | `string(20)` |  |
| storage_type_name | `string(100)` |  |
| storage_class | `enum(['bulk', 'rack', 'floor', 'refrigerated', 'hazmat', 'high_security', 'quarantine', ])` | default('rack') |
| capacity_management | `enum(['no_check', 'total_weight', 'total_qty', 'occupied_bins', ])` | default('no_check') |
| max_weight | `decimal(10, 2)` | nullable |
| max_quantity | `decimal(18, 4)` | nullable |
| total_bins | `unsignedInteger` | nullable |
| current_utilization_percent | `decimal(5, 2)` | default(0) |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['warehouse_id', 'storage_type_code'], 'st_warehouse_code_unq')`
- `$table->index(['organization_id', 'warehouse_id'], 'st_org_warehouse_idx')`

### units_of_measure

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| name | `string(50)` | e.g., "Kilogram", "Piece", "Box" |
| symbol | `string(10)` | e.g., "kg", "pc", "box" |
| base_unit_id | `foreignId` | nullable, constrained('units_of_measure'), nullOnDelete |
| conversion_factor | `decimal(18, 8)` | default(1) — How many base units |
| code | `string(20)` | nullable — e.g., "KG", "PCS" |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'symbol'])`

### warehouses

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| branch_id | `foreignId` | nullable, constrained, nullOnDelete |
| name | `string(100)` |  |
| code | `string(20)` |  |
| address | `text` | nullable |
| city | `string(100)` | nullable |
| country_code | `string(2)` | nullable |
| phone | `string(20)` | nullable |
| email | `string(100)` | nullable |
| manager_id | `foreignId` | nullable, constrained('users'), nullOnDelete |
| is_default | `boolean` | default(false) |
| is_active | `boolean` | default(true) |
| allow_negative_stock | `boolean` | default(false) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'code'])`

### cross_docking_orders

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| warehouse_id | `foreignId` | constrained('warehouses'), cascadeOnDelete |
| inbound_source_type | `string(30)` | comment('purchase_order/transfer_order/return') |
| inbound_source_id | `unsignedBigInteger` |  |
| outbound_dest_type | `string(30)` | comment('sales_order/transfer_order/delivery') |
| outbound_dest_id | `unsignedBigInteger` |  |
| planned_date | `dateTime` |  |
| actual_date | `dateTime` | nullable |
| status | `string(20)` | default('planned'), comment('planned/in_progress/completed/cancelled') |
| dock_door_id | `unsignedBigInteger` | nullable |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['warehouse_id', 'status'], 'xdock_wh_status_idx')`
- `$table->index(['status', 'planned_date'], 'xdock_status_date_idx')`
- `$table->index(['inbound_source_type', 'inbound_source_id'], 'xdock_inbound_idx')`
- `$table->index(['outbound_dest_type', 'outbound_dest_id'], 'xdock_outbound_idx')`

### cycle_count_plans

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| plan_name | `string` |  |
| warehouse_id | `unsignedBigInteger` |  |
| count_frequency | `enum(['A', 'B', 'C', 'custom'])` |  |
| products_per_day | `unsignedSmallInteger` | nullable |
| scheduled_date | `date` | nullable |
| status | `enum(['draft', 'active', 'paused', 'completed'])` | default('draft') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete()`
- `$table->foreign('warehouse_id', 'cc_plan_wh_fk')->references('id')->on('warehouses')->cascadeOnDelete()`

### cycle_count_sessions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| plan_id | `unsignedBigInteger` | nullable |
| warehouse_id | `unsignedBigInteger` |  |
| session_date | `date` |  |
| counted_by | `unsignedBigInteger` |  |
| status | `enum(['open', 'in_progress', 'completed', 'posted'])` | default('open') |
| started_at | `timestamp` | nullable |
| completed_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete()`
- `$table->foreign('plan_id', 'cc_sess_plan_fk')->references('id')->on('cycle_count_plans')->nullOnDelete()`
- `$table->foreign('warehouse_id', 'cc_sess_wh_fk')->references('id')->on('warehouses')->cascadeOnDelete()`
- `$table->foreign('counted_by', 'cc_sess_usr_fk')->references('id')->on('users')->cascadeOnDelete()`

### ewm_storage_types

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| warehouse_id | `foreignId` | constrained('warehouses'), cascadeOnDelete |
| code | `string(20)` | BLK, SHF, HBY, PLT, FRZ |
| name | `string(100)` |  |
| type | `enum(['bulk', 'shelving', 'high_bay', 'pallet', 'freezer', 'hazmat', 'open_storage'])` |  |
| allow_partial_putaway | `boolean` | default(true) |
| mixed_storage | `boolean` | default(false) |
| max_weight_kg | `unsignedSmallInteger` | nullable |
| putaway_strategy | `enum(['fifo', 'fefo', 'lifo', 'nearest_bin', 'fixed_bin', 'max_fill', 'open_storage'])` | default('fifo') |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['warehouse_id', 'code'])`
- `$table->index(['organization_id', 'warehouse_id'])`

### ewm_putaway_rules

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| warehouse_id | `foreignId` | constrained('warehouses'), cascadeOnDelete |
| storage_type_id | `foreignId` | nullable, constrained('ewm_storage_types'), nullOnDelete |
| product_id | `unsignedBigInteger` | nullable |
| category_id | `unsignedBigInteger` | nullable |
| priority | `unsignedSmallInteger` | default(10) — lower = higher priority |
| strategy | `enum(['fifo', 'fefo', 'lifo', 'nearest_bin', 'fixed_bin', 'max_fill'])` |  |
| fixed_bin_code | `string(50)` | nullable — for fixed-bin strategy |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'warehouse_id', 'priority'])`

### ewm_storage_sections

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| warehouse_id | `foreignId` | constrained('warehouses'), cascadeOnDelete |
| storage_type_id | `foreignId` | constrained('ewm_storage_types'), restrictOnDelete |
| code | `string(20)` |  |
| name | `string(100)` |  |
| velocity_class | `enum(['A', 'B', 'C', 'D'])` | default('B') — A=fast-moving |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['warehouse_id', 'code'])`

### ewm_bins

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| warehouse_id | `foreignId` | constrained('warehouses'), cascadeOnDelete |
| storage_type_id | `foreignId` | constrained('ewm_storage_types'), restrictOnDelete |
| storage_section_id | `foreignId` | nullable, constrained('ewm_storage_sections'), nullOnDelete |
| bin_code | `string(50)` | A-01-01-01 (aisle-row-column-level) |
| aisle | `string(10)` | nullable |
| row_number | `string(10)` | nullable |
| column_number | `string(10)` | nullable |
| level | `string(10)` | nullable |
| max_weight_kg | `decimal(10, 2)` | nullable |
| max_volume_m3 | `decimal(10, 4)` | nullable |
| current_weight_kg | `decimal(10, 2)` | default(0) |
| fill_pct | `decimal(5, 2)` | default(0) |
| status | `enum(['active', 'blocked', 'inactive', 'reserved'])` | default('active') |
| mixed_products | `boolean` | default(false) |
| current_product_id | `unsignedBigInteger` | nullable — for single-product bins |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['warehouse_id', 'bin_code'])`
- `$table->index(['organization_id', 'warehouse_id', 'status'])`
- `$table->index(['warehouse_id', 'storage_type_id', 'fill_pct'])`

### goods_issues

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` | index |
| branch_id | `unsignedBigInteger` | nullable |
| gi_number | `string` | unique |
| gi_date | `date` |  |
| movement_type | `string` | sales_delivery, production_issue, scrapping, transfer, other |
| reference_type | `string` | nullable |
| reference_id | `unsignedBigInteger` | nullable |
| warehouse_id | `unsignedBigInteger` |  |
| status | `string` | default('draft') — draft, posted, reversed |
| total_quantity | `decimal(10, 4)` | default(0) |
| total_value | `decimal(15, 4)` | default(0) |
| notes | `text` | nullable |
| posted_by | `unsignedBigInteger` | nullable |
| posted_at | `dateTime` | nullable |
| reversed_by | `unsignedBigInteger` | nullable |
| reversed_at | `dateTime` | nullable |
| reversal_reason | `string` | nullable |
| journal_entry_id | `unsignedBigInteger` | nullable |
| created_by | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['reference_type', 'reference_id'])`
- `$table->index(['organization_id', 'gi_date'])`
- `$table->index(['organization_id', 'status'])`

Foreign keys:

- `$table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete()`
- `$table->foreign('warehouse_id')->references('id')->on('warehouses')`
- `$table->foreign('posted_by')->references('id')->on('users')->nullOnDelete()`
- `$table->foreign('reversed_by')->references('id')->on('users')->nullOnDelete()`
- `$table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete()`
- `$table->foreign('created_by')->references('id')->on('users')->nullOnDelete()`

### physical_inventory_documents

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string(36)` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| document_number | `string(30)` |  |
| warehouse_id | `foreignId` | constrained('warehouses') |
| count_date | `date` |  |
| inventory_type | `enum(['full', 'cycle', 'spot'])` | default('full') |
| status | `enum(['created', 'in_progress', 'counted', 'posted', 'cancelled'])` | default('created') |
| assigned_to | `foreignId` | nullable, constrained('users'), nullOnDelete |
| counted_at | `timestamp` | nullable |
| posted_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| posted_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'document_number'], 'pid_org_doc_unique')`
- `$table->index(['organization_id', 'status'], 'pid_org_status_idx')`
- `$table->index(['warehouse_id', 'count_date'], 'pid_wh_date_idx')`

### stock_adjustments

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| warehouse_id | `foreignId` | constrained, cascadeOnDelete |
| adjustment_number | `string(50)` |  |
| adjustment_date | `date` |  |
| reason | `enum(['damage', 'theft', 'expiry', 'count_correction', 'opening_balance', 'other', ])` |  |
| notes | `text` | nullable |
| status | `enum(['draft', 'posted', 'cancelled'])` | default('draft') |
| posted_at | `timestamp` | nullable |
| posted_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'adjustment_number'])`
- `$table->index(['organization_id', 'status'], 'sa_org_status_idx')`
- `$table->index(['organization_id', 'warehouse_id'], 'sa_org_warehouse_idx')`
- `$table->index(['organization_id', 'adjustment_date'], 'sa_org_date_idx')`

### stock_transfers

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| transfer_number | `string(50)` |  |
| transfer_date | `date` |  |
| expected_arrival_date | `date` | nullable |
| from_warehouse_id | `foreignId` | constrained('warehouses') |
| to_warehouse_id | `foreignId` | constrained('warehouses') |
| notes | `text` | nullable |
| status | `enum(['draft', 'in_transit', 'received', 'cancelled'])` | default('draft') |
| shipped_at | `timestamp` | nullable |
| shipped_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| received_at | `timestamp` | nullable |
| received_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'transfer_number'])`
- `$table->index(['organization_id', 'status'], 'st_org_status_idx')`
- `$table->index(['organization_id', 'transfer_date'], 'st_org_date_idx')`
- `$table->index(['organization_id', 'from_warehouse_id'], 'st_org_from_wh_idx')`
- `$table->index(['organization_id', 'to_warehouse_id'], 'st_org_to_wh_idx')`

### warehouse_locations

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| warehouse_id | `foreignId` | constrained, cascadeOnDelete |
| parent_id | `foreignId` | nullable, constrained('warehouse_locations'), nullOnDelete |
| name | `string(50)` |  |
| code | `string(20)` |  |
| type | `enum(['zone', 'aisle', 'rack', 'shelf', 'bin'])` | default('bin') |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['warehouse_id', 'code'])`

### warehouse_transfer_orders

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string(36)` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| to_number | `string(30)` |  |
| warehouse_id | `foreignId` | constrained('warehouses'), cascadeOnDelete |
| movement_type | `enum(['goods_receipt', 'goods_issue', 'internal_transfer', 'replenishment'])` | default('internal_transfer') |
| source_document_type | `string(50)` | nullable |
| source_document_ref | `string(50)` | nullable |
| source_location_id | `foreignId` | nullable, constrained('warehouse_locations'), nullOnDelete |
| dest_location_id | `foreignId` | nullable, constrained('warehouse_locations'), nullOnDelete |
| status | `enum(['created', 'in_progress', 'confirmed', 'cancelled'])` | default('created') |
| assigned_to | `foreignId` | nullable, constrained('users'), nullOnDelete |
| confirmed_at | `timestamp` | nullable |
| created_by | `foreignId` | constrained('users') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'to_number'], 'wto_org_number_unique')`
- `$table->index(['warehouse_id', 'status'], 'wto_wh_status_idx')`

### wave_plans

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| warehouse_id | `foreignId` | constrained('warehouses'), cascadeOnDelete |
| wave_number | `string` |  |
| wave_type | `enum(['outbound', 'replenishment', 'returns'])` | default('outbound') |
| status | `enum(['draft', 'released', 'picking', 'completed', 'cancelled'])` | default('draft') |
| planned_date | `date` |  |
| total_orders | `integer` | default(0) |
| total_lines | `integer` | default(0) |
| total_units | `decimal(12, 4)` | default(0) |
| released_at | `timestamp` | nullable |
| completed_at | `timestamp` | nullable |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'status'])`
- `$table->index(['warehouse_id', 'status'])`

### picking_lists

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| wave_plan_id | `foreignId` | nullable, constrained('wave_plans'), nullOnDelete |
| warehouse_id | `foreignId` | constrained('warehouses'), cascadeOnDelete |
| list_number | `string` |  |
| picker_id | `foreignId` | nullable, constrained('users'), nullOnDelete |
| status | `enum(['pending', 'assigned', 'in_progress', 'completed', 'partial', 'cancelled'])` | default('pending') |
| picking_type | `enum(['single_order', 'multi_order', 'zone', 'cluster'])` | default('single_order') |
| total_lines | `integer` | default(0) |
| picked_lines | `integer` | default(0) |
| assigned_at | `timestamp` | nullable |
| started_at | `timestamp` | nullable |
| completed_at | `timestamp` | nullable |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'status'])`
- `$table->index(['warehouse_id', 'picker_id'])`

### wave_plan_orders

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| wave_plan_id | `foreignId` | constrained('wave_plans'), cascadeOnDelete |
| order_type | `enum(['sales_order', 'stock_transfer', 'purchase_return'])` | default('sales_order') |
| order_id | `unsignedBigInteger` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['wave_plan_id', 'order_type', 'order_id'])`

### yard_zones

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| warehouse_id | `foreignId` | constrained('warehouses'), cascadeOnDelete |
| zone_code | `string(20)` |  |
| name | `string(100)` |  |
| zone_type | `string(20)` | default('staging'), comment('staging/parking/inspection/dock') |
| capacity_vehicles | `unsignedInteger` | nullable |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['warehouse_id', 'is_active'], 'yard_zone_wh_active_idx')`
- `$table->unique(['warehouse_id', 'zone_code'], 'yard_zone_wh_code_uq')`

### dock_doors

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| warehouse_id | `foreignId` | constrained('warehouses'), cascadeOnDelete |
| door_code | `string(10)` |  |
| door_type | `string(20)` | default('combined'), comment('inbound/outbound/combined') |
| yard_zone_id | `foreignId` | nullable, constrained('yard_zones'), nullOnDelete |
| is_active | `boolean` | default(true) |
| status | `string(20)` | default('available'), comment('available/occupied/maintenance') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['warehouse_id', 'status'], 'dock_door_wh_status_idx')`
- `$table->unique(['warehouse_id', 'door_code'], 'dock_door_wh_code_uq')`

### dim_warehouse

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `unsignedBigInteger` |  |
| warehouse_id | `unsignedBigInteger` | nullable |
| warehouse_code | `string(30)` |  |
| warehouse_name | `string` |  |
| location | `string(100)` | nullable |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('organization_id', 'dim_warehouse_org_id_idx')`

Foreign keys:

- `$table->foreign('warehouse_id', 'dim_warehouse_wh_id_fk')->references('id')->on('warehouses')->onDelete('set null')`

## 0200_maintenance.php

### equipment_categories

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `unsignedBigInteger` |  |
| name | `string` |  |
| description | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'name'])`

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete()`

### functional_locations

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| parent_id | `unsignedBigInteger` | nullable |
| code | `string` |  |
| name | `string` |  |
| description | `text` | nullable |
| location_type | `enum(['plant', 'area', 'line', 'machine', 'component'])` | default('area') |
| branch_id | `unsignedBigInteger` | nullable |
| address | `string` | nullable |
| is_active | `boolean` | default(true) |
| deleted_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| planner_group | `string` | nullable |

Indexes:

- `$table->unique(['organization_id', 'code'])`
- `$table->index('organization_id')`

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete()`
- `$table->foreign('parent_id')->references('id')->on('functional_locations')->nullOnDelete()`
- `$table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete()`

### equipment

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| functional_location_id | `unsignedBigInteger` | nullable |
| equipment_category_id | `unsignedBigInteger` | nullable |
| equipment_number | `string` |  |
| name | `string` |  |
| description | `text` | nullable |
| manufacturer | `string` | nullable |
| model | `string` | nullable |
| serial_number | `string` | nullable |
| acquisition_date | `date` | nullable |
| acquisition_cost | `decimal(12, 2)` | nullable |
| warranty_expiry | `date` | nullable |
| status | `enum(['active', 'under_maintenance', 'decommissioned', 'scrapped'])` | default('active') |
| last_maintenance_date | `date` | nullable |
| next_maintenance_date | `date` | nullable |
| notes | `text` | nullable |
| created_by | `unsignedBigInteger` | nullable |
| deleted_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'equipment_number'])`
- `$table->index(['organization_id', 'status'])`

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete()`
- `$table->foreign('functional_location_id')->references('id')->on('functional_locations')->nullOnDelete()`
- `$table->foreign('equipment_category_id')->references('id')->on('equipment_categories')->nullOnDelete()`
- `$table->foreign('created_by')->references('id')->on('users')->nullOnDelete()`

### maintenance_condition_rules

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| rule_name | `string(100)` |  |
| equipment_id | `unsignedBigInteger` |  |
| measurement_point | `string(100)` |  |
| condition_operator | `enum(['greater_than', 'less_than', 'equals', 'between'])` | default('greater_than') |
| threshold_value | `decimal(15, 4)` |  |
| threshold_value_to | `decimal(15, 4)` | nullable |
| unit_of_measure | `string(20)` | nullable |
| trigger_action | `enum(['create_order', 'notify', 'both'])` | default('both') |
| maintenance_type | `enum(['inspection', 'repair', 'overhaul', 'replacement'])` | default('inspection') |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'equipment_id'], 'mcr_org_equip_idx')`

### maintenance_fault_codes

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| code | `string(20)` | unique |
| description | `string(255)` |  |
| fault_type | `enum(['mechanical', 'electrical', 'hydraulic', 'software', 'operator', 'wear', 'other'])` | default('other') |
| cause | `text` | nullable |
| recommended_action | `text` | nullable |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

### maintenance_kpis

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| equipment_id | `unsignedBigInteger` | nullable, comment('NULL = org-level aggregate') |
| period_year | `string(4)` |  |
| period_month | `string(2)` |  |
| mtbf_hours | `decimal(10, 2)` | default(0), comment('Mean Time Between Failures') |
| mttr_hours | `decimal(10, 2)` | default(0), comment('Mean Time To Repair') |
| availability_pct | `decimal(5, 2)` | default(0), comment('(MTBF / (MTBF + MTTR)) * 100') |
| oee_pct | `decimal(5, 2)` | default(0), comment('Overall Equipment Effectiveness') |
| breakdown_count | `integer` | default(0) |
| total_downtime_hours | `decimal(10, 2)` | default(0) |
| planned_maintenance_hours | `decimal(10, 2)` | default(0) |
| unplanned_maintenance_hours | `decimal(10, 2)` | default(0) |
| maintenance_cost | `decimal(15, 4)` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'equipment_id', 'period_year', 'period_month'], 'maintenance_kpis_org_equipment_period_yr_mo_uniq')`
- `$table->index(['organization_id', 'period_year', 'period_month'])`

### maintenance_measurements

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| equipment_id | `unsignedBigInteger` |  |
| measurement_point | `string(100)` |  |
| measurement_value | `decimal(15, 4)` |  |
| unit_of_measure | `string(20)` | nullable |
| measured_at | `timestamp` |  |
| recorded_by | `foreignId` | constrained('users') |
| threshold_breached | `boolean` | default(false) |
| triggered_rule_id | `foreignId` | nullable, constrained('maintenance_condition_rules'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['equipment_id', 'measurement_point', 'measured_at'], 'mm_equip_point_date_idx')`

### maintenance_notifications

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| notification_number | `string(20)` | unique |
| notification_type | `enum(['M1', 'M2', 'M3', 'S1', 'S4'])` | default('M2') |
| short_text | `string(200)` |  |
| long_text | `text` | nullable |
| equipment_id | `unsignedBigInteger` | nullable |
| functional_location_code | `string(50)` | nullable |
| priority | `enum(['1_very_high', '2_high', '3_medium', '4_low'])` | default('3_medium') |
| malfunction_start_date | `date` | nullable |
| malfunction_end_date | `date` | nullable |
| malfunction_duration_hours | `decimal(8, 2)` | nullable |
| breakdown | `boolean` | default(false) |
| production_stop | `boolean` | default(false) |
| damage_code | `string(20)` | nullable |
| cause_code | `string(20)` | nullable |
| activity_code | `string(20)` | nullable |
| cause_text | `text` | nullable |
| task_text | `text` | nullable |
| status | `enum(['OSNO', 'NOPR', 'INIT', 'ORAS', 'NOCO', 'COMP'])` | default('OSNO') |
| maintenance_order_id | `unsignedBigInteger` | nullable |
| reported_by | `foreignId` | constrained('users'), restrictOnDelete |
| responsible_id | `foreignId` | nullable, constrained('users'), nullOnDelete |
| completed_at | `timestamp` | nullable |
| completion_text | `text` | nullable |
| created_by | `foreignId` | constrained('users'), restrictOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'status'])`
- `$table->index(['organization_id', 'notification_type'], 'maintenance_notifications_org_notif_type_idx')`
- `$table->index(['organization_id', 'equipment_id'])`
- `$table->index(['organization_id', 'priority'])`

Foreign keys:

- `$table->foreign('equipment_id')->references('id')->on('equipment')->nullOnDelete()`

### maintenance_notification_items

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| notification_id | `foreignId` | constrained('maintenance_notifications'), cascadeOnDelete |
| item_number | `unsignedSmallInteger` |  |
| short_text | `string(200)` |  |
| long_text | `text` | nullable |
| damage_code | `string(20)` | nullable |
| cause_code | `string(20)` | nullable |
| status | `enum(['outstanding', 'in_process', 'completed', 'cleared'])` | default('outstanding') |
| completed_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['notification_id', 'item_number'], 'maintenance_notification_items_notif_item_num_uniq')`

### maintenance_notification_tasks

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| notification_id | `foreignId` | constrained('maintenance_notifications'), cascadeOnDelete |
| task_number | `unsignedSmallInteger` |  |
| description | `string(200)` |  |
| details | `text` | nullable |
| assigned_to | `foreignId` | nullable, constrained('users'), nullOnDelete |
| planned_start_date | `date` | nullable |
| planned_end_date | `date` | nullable |
| status | `enum(['outstanding', 'in_process', 'completed', 'cleared'])` | default('outstanding') |
| completed_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['notification_id', 'task_number'], 'maintenance_notification_tasks_notif_task_num_uniq')`

### maintenance_order_settlements

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| maintenance_order_id | `unsignedBigInteger` |  |
| settlement_rule_type | `string(30)` | comment('full/partial') |
| receiver_type | `string(30)` | comment('cost_center/asset/order/wbs') |
| receiver_id | `unsignedBigInteger` |  |
| percentage | `decimal(5, 2)` | default(100) |
| settled_amount | `decimal(18, 4)` |  |
| settlement_date | `date` |  |
| fiscal_year | `smallInteger` |  |
| period | `tinyInteger` |  |
| journal_entry_id | `foreignId` | nullable, constrained('journal_entries'), nullOnDelete |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['maintenance_order_id', 'settlement_date'], 'mo_settle_order_date_idx')`
- `$table->index(['receiver_type', 'receiver_id'], 'mo_settle_recv_idx')`

### maintenance_plans

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| equipment_id | `unsignedBigInteger` |  |
| name | `string` |  |
| maintenance_type | `enum(['preventive', 'predictive', 'condition_based'])` | default('preventive') |
| frequency_type | `enum(['daily', 'weekly', 'monthly', 'quarterly', 'yearly', 'hours', 'kilometers'])` | default('monthly') |
| frequency_value | `unsignedInteger` | default(1) |
| estimated_duration_hours | `decimal(5, 2)` | default(1.00) |
| description | `text` | nullable |
| tasks | `json` | nullable |
| is_active | `boolean` | default(true) |
| last_generated_at | `timestamp` | nullable |
| created_by | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('organization_id')`
- `$table->index('equipment_id')`

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete()`
- `$table->foreign('equipment_id')->references('id')->on('equipment')->cascadeOnDelete()`
- `$table->foreign('created_by')->references('id')->on('users')->nullOnDelete()`

### maintenance_orders

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| order_number | `string` |  |
| maintenance_plan_id | `unsignedBigInteger` | nullable |
| equipment_id | `unsignedBigInteger` |  |
| order_type | `enum(['preventive', 'corrective', 'emergency', 'inspection'])` | default('corrective') |
| priority | `enum(['low', 'medium', 'high', 'critical'])` | default('medium') |
| status | `enum(['open', 'in_progress', 'on_hold', 'completed', 'cancelled'])` | default('open') |
| description | `text` |  |
| scheduled_start | `dateTime` | nullable |
| scheduled_end | `dateTime` | nullable |
| actual_start | `dateTime` | nullable |
| actual_end | `dateTime` | nullable |
| assigned_to | `unsignedBigInteger` | nullable |
| estimated_cost | `decimal(12, 2)` | nullable |
| actual_cost | `decimal(12, 2)` | nullable |
| downtime_hours | `decimal(6, 2)` | nullable |
| resolution_notes | `text` | nullable |
| created_by | `unsignedBigInteger` | nullable |
| deleted_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'order_number'])`
- `$table->index(['organization_id', 'status', 'priority'])`
- `$table->index(['equipment_id', 'status'])`

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete()`
- `$table->foreign('maintenance_plan_id')->references('id')->on('maintenance_plans')->nullOnDelete()`
- `$table->foreign('equipment_id')->references('id')->on('equipment')->cascadeOnDelete()`
- `$table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete()`
- `$table->foreign('created_by')->references('id')->on('users')->nullOnDelete()`

### maintenance_order_tasks

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| maintenance_order_id | `unsignedBigInteger` |  |
| task_description | `string` |  |
| is_safety_critical | `boolean` | default(false) |
| is_completed | `boolean` | default(false) |
| completed_at | `timestamp` | nullable |
| completed_by | `unsignedBigInteger` | nullable |
| notes | `text` | nullable |
| sort_order | `smallInteger` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('maintenance_order_id')`

Foreign keys:

- `$table->foreign('maintenance_order_id')->references('id')->on('maintenance_orders')->cascadeOnDelete()`
- `$table->foreign('completed_by')->references('id')->on('users')->nullOnDelete()`

### maintenance_permits

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| maintenance_order_id | `unsignedBigInteger` | nullable |
| permit_number | `string(50)` |  |
| permit_type | `enum(['hot_work', 'confined_space', 'electrical_isolation', 'height_work', 'chemical', 'general'])` | default('general') |
| status | `enum(['requested', 'approved', 'active', 'suspended', 'closed', 'cancelled'])` | default('requested') |
| valid_from | `dateTime` | nullable |
| valid_until | `dateTime` | nullable |
| location | `string(255)` | nullable |
| work_description | `text` | nullable |
| hazards_identified | `text` | nullable |
| precautions_required | `text` | nullable |
| requested_by | `unsignedBigInteger` | nullable |
| approved_by | `unsignedBigInteger` | nullable |
| approved_at | `dateTime` | nullable |
| closed_by | `unsignedBigInteger` | nullable |
| closed_at | `dateTime` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'permit_number'], 'mp_org_number_unq')`
- `$table->index(['organization_id', 'status'], 'mp_org_status_idx')`
- `$table->index(['maintenance_order_id'], 'mp_mo_idx')`

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')`
- `$table->foreign('maintenance_order_id', 'mp_mo_fk')->references('id')->on('maintenance_orders')`
- `$table->foreign('requested_by', 'mp_requested_by_fk')->references('id')->on('users')`
- `$table->foreign('approved_by', 'mp_approved_by_fk')->references('id')->on('users')`
- `$table->foreign('closed_by', 'mp_closed_by_fk')->references('id')->on('users')`

### maintenance_root_cause_analyses

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| maintenance_order_id | `unsignedBigInteger` |  |
| equipment_id | `unsignedBigInteger` |  |
| fault_code_id | `unsignedBigInteger` | nullable |
| rca_method | `enum(['5_why', 'fishbone', 'fault_tree', 'fmea', 'other'])` | default('5_why') |
| whys | `json` | nullable, comment('For 5-Why: array of why strings') |
| root_cause | `text` | nullable |
| contributing_factors | `text` | nullable |
| corrective_actions | `text` | nullable |
| preventive_actions | `text` | nullable |
| status | `enum(['open', 'in_progress', 'closed'])` | default('open') |
| assigned_to | `unsignedBigInteger` | nullable |
| target_date | `date` | nullable |
| closed_date | `date` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'equipment_id'], 'maintenance_root_cause_analyses_org_equipment_idx')`
- `$table->index(['organization_id', 'status'])`

### maintenance_service_orders

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| service_order_number | `string(30)` | unique |
| maintenance_order_id | `unsignedBigInteger` | nullable |
| equipment_id | `unsignedBigInteger` | nullable |
| vendor_id | `unsignedBigInteger` | nullable, comment('FK to suppliers/vendors') |
| status | `enum(['draft', 'issued', 'confirmed', 'in_progress', 'completed', 'cancelled'])` | default('draft') |
| service_type | `enum(['repair', 'inspection', 'installation', 'calibration', 'overhaul'])` | default('repair') |
| description | `text` |  |
| requested_date | `date` |  |
| due_date | `date` |  |
| completed_date | `date` | nullable |
| estimated_cost | `decimal(15, 4)` | default(0) |
| actual_cost | `decimal(15, 4)` | default(0) |
| sla_response_hours | `string(10)` | nullable, comment('SLA: response time in hours') |
| sla_resolution_hours | `string(10)` | nullable, comment('SLA: resolution time in hours') |
| sla_response_due_at | `timestamp` | nullable |
| sla_resolution_due_at | `timestamp` | nullable |
| vendor_responded_at | `timestamp` | nullable |
| sla_breached | `boolean` | default(false) |
| purchase_order_id | `unsignedBigInteger` | nullable, comment('Linked PO') |
| bill_id | `unsignedBigInteger` | nullable, comment('Vendor invoice when completed') |
| vendor_notes | `text` | nullable |
| created_by | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'status'])`
- `$table->index(['organization_id', 'vendor_id'])`

### maintenance_task_lists

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| task_list_number | `string` | unique |
| description | `string` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete()`

### permit_safety_checks

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| maintenance_permit_id | `unsignedBigInteger` |  |
| check_description | `string(255)` |  |
| is_mandatory | `boolean` | default(true) |
| is_completed | `boolean` | default(false) |
| completed_by | `unsignedBigInteger` | nullable |
| completed_at | `dateTime` | nullable |
| remarks | `text` | nullable |
| sort_order | `unsignedSmallInteger` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['maintenance_permit_id'], 'psc_permit_idx')`

Foreign keys:

- `$table->foreign('organization_id', 'psc_org_fk')->references('id')->on('organizations')`
- `$table->foreign('maintenance_permit_id', 'psc_permit_fk')->references('id')->on('maintenance_permits')`
- `$table->foreign('completed_by', 'psc_completed_by_fk')->references('id')->on('users')`

### vehicles

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| fleet_number | `string(20)` |  |
| license_plate | `string(20)` |  |
| make | `string(50)` |  |
| model | `string(50)` |  |
| year | `smallInteger` |  |
| vin | `string(50)` | nullable |
| vehicle_type | `string(30)` | default('car'), comment('car/van/truck/motorcycle/bus/other') |
| fuel_type | `string(20)` | default('petrol'), comment('petrol/diesel/electric/hybrid/cng') |
| color | `string(30)` | nullable |
| department_id | `foreignId` | nullable, constrained('departments'), nullOnDelete |
| current_mileage_km | `integer` | default(0) |
| last_service_km | `integer` | nullable |
| next_service_km | `integer` | nullable |
| insurance_expiry | `date` | nullable |
| registration_expiry | `date` | nullable |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'fleet_number'], 'vehicle_org_fleet_idx')`
- `$table->index(['is_active', 'insurance_expiry'], 'vehicle_active_ins_idx')`

### fuel_logs

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| vehicle_id | `foreignId` | constrained('vehicles'), cascadeOnDelete |
| log_date | `date` |  |
| odometer_reading | `integer` |  |
| fuel_quantity_liters | `decimal(10, 3)` |  |
| fuel_cost | `decimal(18, 4)` |  |
| currency_code | `string(3)` |  |
| fuel_type | `string(20)` |  |
| station | `string(100)` | nullable |
| filled_by | `foreignId` | nullable, constrained('employees'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['vehicle_id', 'log_date'], 'fuel_veh_date_idx')`

### mileage_logs

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| vehicle_id | `foreignId` | constrained('vehicles'), cascadeOnDelete |
| log_date | `date` |  |
| odometer_start | `integer` |  |
| odometer_end | `integer` |  |
| distance_km | `integer` |  |
| trip_purpose | `string(100)` | nullable |
| driver_id | `foreignId` | nullable, constrained('employees'), nullOnDelete |
| route | `string(200)` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['vehicle_id', 'log_date'], 'mileage_veh_date_idx')`

### vehicle_assignments

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| vehicle_id | `foreignId` | constrained('vehicles'), cascadeOnDelete |
| driver_id | `foreignId` | nullable, constrained('employees'), nullOnDelete |
| assigned_from | `dateTime` |  |
| assigned_to | `dateTime` | nullable |
| purpose | `string(100)` | nullable |
| is_current | `boolean` | default(false) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['vehicle_id', 'is_current'], 'veh_assign_veh_curr_idx')`
- `$table->index(['driver_id', 'is_current'], 'veh_assign_drv_curr_idx')`

### vehicle_maintenance_records

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| vehicle_id | `foreignId` | constrained('vehicles'), cascadeOnDelete |
| maintenance_type | `string(30)` | comment('scheduled/unscheduled/repair/inspection') |
| service_date | `date` |  |
| odometer_reading | `integer` | nullable |
| description | `text` |  |
| cost | `decimal(18, 4)` | nullable |
| currency_code | `string(3)` | nullable |
| service_provider | `string(100)` | nullable |
| next_service_date | `date` | nullable |
| next_service_km | `integer` | nullable |
| maintenance_order_id | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['vehicle_id', 'service_date'], 'veh_maint_veh_date_idx')`

## 0210_manufacturing.php

### audit_plans

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| plan_number | `string(50)` | unique |
| title | `string` |  |
| audit_type | `enum(['internal', 'supplier', 'customer', 'regulatory', 'certification'])` | default('internal') |
| planned_start | `date` |  |
| planned_end | `date` |  |
| lead_auditor_id | `unsignedBigInteger` | nullable |
| status | `enum(['draft', 'approved', 'in_progress', 'completed', 'cancelled'])` | default('draft') |
| scope | `text` | nullable |
| objectives | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete()`
- `$table->foreign('lead_auditor_id', 'audit_plan_auditor_fk')->references('id')->on('users')->nullOnDelete()`

### audit_checklists

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| audit_plan_id | `unsignedBigInteger` |  |
| item_number | `string(20)` |  |
| question | `text` |  |
| response | `enum(['yes', 'no', 'partial', 'na'])` | nullable |
| remarks | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('audit_plan_id', 'audit_cl_plan_fk')->references('id')->on('audit_plans')->cascadeOnDelete()`

### audit_reports

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| audit_plan_id | `unsignedBigInteger` |  |
| report_date | `date` |  |
| executive_summary | `text` | nullable |
| conclusions | `text` | nullable |
| overall_rating | `enum(['satisfactory', 'needs_improvement', 'unsatisfactory'])` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('audit_plan_id', 'audit_rpt_plan_fk')->references('id')->on('audit_plans')->cascadeOnDelete()`

### bom_alternatives

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| product_id | `foreignId` | constrained('products'), cascadeOnDelete, name('ba_product_fk') |
| alternative_number | `unsignedSmallInteger` |  |
| alternative_name | `string(100)` | nullable |
| bom_template_id | `foreignId` | nullable, constrained('bom_templates'), nullOnDelete, name('ba_bom_template_fk') |
| valid_from | `date` |  |
| valid_to | `date` | nullable |
| is_default | `boolean` | default(false) |
| usage_type | `enum(['production', 'engineering', 'costing', 'plant_maintenance'])` | default('production') |
| lot_size_from | `decimal(18, 4)` | nullable |
| lot_size_to | `decimal(18, 4)` | nullable |
| status | `enum(['active', 'inactive', 'obsolete'])` | default('active') |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'product_id', 'alternative_number'], 'ba_org_prod_alt_unq')`
- `$table->index(['organization_id', 'product_id', 'valid_from'], 'ba_org_prod_valid_idx')`

### bom_co_products

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| bom_template_id | `foreignId` | constrained('bom_templates'), cascadeOnDelete, name('bcp_bom_fk') |
| product_id | `foreignId` | constrained('products'), cascadeOnDelete, name('bcp_product_fk') |
| co_product_type | `enum(['co_product', 'by_product', 'scrap'])` | default('co_product') |
| quantity_per_base | `decimal(18, 4)` |  |
| unit_of_measure | `string(20)` | nullable |
| cost_allocation_percent | `decimal(5, 2)` | default(0) |
| is_valuated | `boolean` | default(true) |
| valid_from | `date` | nullable |
| valid_to | `date` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['bom_template_id'], 'bcp_bom_idx')`
- `$table->index(['product_id'], 'bcp_product_idx')`

### capa_records

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| capa_number | `string(50)` | unique |
| capa_type | `enum(['corrective', 'preventive'])` | default('corrective') |
| source_type | `string` | nullable |
| source_id | `unsignedBigInteger` | nullable |
| problem_statement | `text` |  |
| root_cause | `text` | nullable |
| priority | `enum(['critical', 'high', 'medium', 'low'])` | default('medium') |
| status | `enum(['open', 'in_progress', 'pending_verification', 'closed', 'cancelled'])` | default('open') |
| owner_id | `unsignedBigInteger` | nullable |
| target_close_date | `date` | nullable |
| actual_close_date | `date` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete()`
- `$table->foreign('owner_id', 'capa_owner_fk')->references('id')->on('users')->nullOnDelete()`

### capa_actions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| capa_record_id | `unsignedBigInteger` |  |
| action_number | `string(20)` |  |
| description | `text` |  |
| assigned_to_id | `unsignedBigInteger` | nullable |
| due_date | `date` |  |
| completed_date | `date` | nullable |
| status | `enum(['pending', 'in_progress', 'completed', 'overdue'])` | default('pending') |
| completion_notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('capa_record_id', 'capa_action_record_fk')->references('id')->on('capa_records')->cascadeOnDelete()`
- `$table->foreign('assigned_to_id', 'capa_action_user_fk')->references('id')->on('users')->nullOnDelete()`

### capa_effectiveness_reviews

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| capa_record_id | `unsignedBigInteger` |  |
| review_date | `date` |  |
| reviewed_by_id | `unsignedBigInteger` |  |
| effectiveness | `enum(['effective', 'partially_effective', 'not_effective'])` | default('effective') |
| evidence | `text` | nullable |
| conclusions | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('capa_record_id', 'capa_eff_record_fk')->references('id')->on('capa_records')->cascadeOnDelete()`
- `$table->foreign('reviewed_by_id', 'capa_eff_reviewer_fk')->references('id')->on('users')`

### cost_versions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| version_code | `string(20)` |  |
| description | `string(200)` | nullable |
| costing_type | `enum(['standard', 'actual', 'planned'])` | default('standard') |
| fiscal_year_id | `foreignId` | nullable, constrained('fiscal_years'), nullOnDelete |
| is_active | `boolean` | default(false) |
| is_default | `boolean` | default(false) |
| marked_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| marked_at | `timestamp` | nullable |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'version_code'], 'cost_versions_org_code_unique')`
- `$table->index(['organization_id', 'is_active'], 'cost_versions_org_active_idx')`
- `$table->index(['organization_id', 'costing_type'], 'cost_versions_org_type_idx')`

### cost_rollup_logs

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| cost_version_id | `foreignId` | nullable, constrained('cost_versions'), nullOnDelete |
| status | `enum(['running', 'completed', 'failed'])` | default('running') |
| run_at | `timestamp` | nullable |
| products_costed | `integer` | default(0) |
| levels_processed | `integer` | default(0) |
| error_message | `text` | nullable |
| created_at | `timestamp` | nullable |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |

Indexes:

- `$table->index(['organization_id', 'status'], 'cost_rollup_org_status_idx')`
- `$table->index('cost_version_id', 'cost_rollup_version_idx')`

### costing_versions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| version_code | `string(20)` |  |
| description | `string(200)` |  |
| valid_from | `date` |  |
| valid_to | `date` | nullable |
| status | `enum(['draft', 'active', 'frozen', 'archived'])` | default('draft') |
| costing_type | `enum(['standard', 'actual', 'planned'])` | default('standard') |
| currency_code | `string(3)` | default('USD') |
| created_by | `unsignedBigInteger` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |
| released_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| released_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'version_code'], 'cv_org_code_unique')`
- `$table->index(['organization_id', 'status'], 'cv_org_status_idx')`

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade')`
- `$table->foreign('created_by')->references('id')->on('users')`

### costing_runs

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| costing_version_id | `unsignedBigInteger` |  |
| run_date | `date` |  |
| products_processed | `unsignedInteger` | default(0) |
| products_failed | `unsignedInteger` | default(0) |
| status | `enum(['running', 'completed', 'failed'])` | default('running') |
| completed_at | `timestamp` | nullable |
| notes | `text` | nullable |
| created_by | `unsignedBigInteger` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'run_date'], 'cr_org_date_idx')`

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade')`
- `$table->foreign('costing_version_id')->references('id')->on('costing_versions')->onDelete('cascade')`
- `$table->foreign('created_by')->references('id')->on('users')`

### engineering_change_objects

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete, name('eao_org_fk') |
| engineering_change_id | `foreignId` | constrained('engineering_changes'), cascadeOnDelete, name('eao_ec_fk') |
| object_type | `enum(['bom', 'routing', 'product', 'drawing'])` | default('bom') |
| object_id | `unsignedBigInteger` |  |
| object_reference | `string(100)` | nullable |
| change_description | `text` | nullable |
| before_value | `json` | nullable |
| after_value | `json` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['engineering_change_id'], 'eao_ec_idx')`
- `$table->index(['object_type', 'object_id'], 'eao_obj_idx')`

### engineering_changes

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| change_number | `string(50)` |  |
| change_type | `enum(['bom_change', 'routing_change', 'product_spec_change', 'drawing_change'])` | default('bom_change') |
| description | `text` |  |
| reason | `text` | nullable |
| status | `enum(['draft', 'submitted', 'approved', 'rejected', 'implemented', 'cancelled'])` | default('draft') |
| effectivity_date | `date` | nullable |
| priority | `enum(['low', 'normal', 'high', 'critical'])` | default('normal') |
| requested_by | `foreignId` | nullable, constrained('users'), nullOnDelete, name('ec_requested_by_fk') |
| approved_by | `foreignId` | nullable, constrained('users'), nullOnDelete, name('ec_approved_by_fk') |
| approved_at | `dateTime` | nullable |
| implemented_at | `dateTime` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'change_number'], 'ec_org_number_unq')`
- `$table->index(['organization_id', 'status'], 'ec_org_status_idx')`

### kanban_supply_areas

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string(36)` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| code | `string(20)` |  |
| name | `string(100)` |  |
| warehouse_id | `foreignId` | constrained('warehouses'), cascadeOnDelete |
| location_id | `foreignId` | nullable, constrained('warehouse_locations'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'code'], 'ksa_org_code_unique')`

### mrp_runs

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| run_date | `dateTime` |  |
| planning_horizon_days | `integer` | default(30) |
| status | `enum(['pending', 'running', 'completed', 'failed'])` | default('pending') |
| total_products_analyzed | `integer` | default(0) |
| total_planned_orders | `integer` | default(0) |
| error_message | `text` | nullable |
| run_by | `foreignId` | constrained('users') |
| completed_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('organization_id')`
- `$table->index(['organization_id', 'status'])`

### planning_simulations

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| name | `string` |  |
| description | `text` | nullable |
| planning_horizon_from | `date` |  |
| planning_horizon_to | `date` |  |
| status | `string(20)` | default('draft') |
| mrp_run_id | `foreignId` | nullable, constrained('mrp_runs'), nullOnDelete |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| run_at | `dateTime` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'status'], 'ltp_sim_org_status_idx')`

### product_cost_collector_items

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), name('pcci_org_fk') |
| product_cost_collector_id | `foreignId` | constrained('product_cost_collectors'), name('pcci_pcc_fk') |
| cost_element_id | `foreignId` | nullable, constrained('cost_elements'), name('pcci_ce_fk') |
| cost_category | `enum(['material', 'labor', 'overhead', 'other'])` | default('material') |
| standard_cost | `decimal(18, 4)` | default(0) |
| actual_cost | `decimal(18, 4)` | default(0) |
| variance | `decimal(18, 4)` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['product_cost_collector_id'], 'pcci_pcc_idx')`

### product_cost_collectors

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations') |
| product_id | `foreignId` | constrained('products'), name('pcc_product_fk') |
| production_line_id | `unsignedBigInteger` | nullable |
| period | `unsignedTinyInteger` |  |
| fiscal_year | `unsignedSmallInteger` |  |
| status | `enum(['open', 'closed'])` | default('open') |
| standard_cost_total | `decimal(18, 4)` | default(0) |
| actual_cost_total | `decimal(18, 4)` | default(0) |
| total_variance | `decimal(18, 4)` | default(0) |
| quantity_produced | `decimal(18, 4)` | default(0) |
| cost_per_unit_standard | `decimal(18, 4)` | default(0) |
| cost_per_unit_actual | `decimal(18, 4)` | default(0) |
| closed_at | `dateTime` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'product_id', 'production_line_id', 'period', 'fiscal_year'], 'pcc_org_prod_line_period_fy_unq')`

### production_resource_tools

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| prt_number | `string(50)` |  |
| prt_name | `string(100)` |  |
| prt_type | `enum(['tool', 'fixture', 'jig', 'test_equipment', 'document', 'program'])` | default('tool') |
| status | `enum(['available', 'in_use', 'maintenance', 'retired'])` | default('available') |
| location | `string(100)` | nullable |
| quantity_available | `unsignedInteger` | default(1) |
| quantity_in_use | `unsignedInteger` | default(0) |
| serial_number | `string(100)` | nullable |
| next_calibration_date | `date` | nullable |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'prt_number'], 'prt_org_number_unq')`
- `$table->index(['organization_id', 'status'], 'prt_org_status_idx')`

### tool_operation_assignments

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete, name('poa_org_fk') |
| production_resource_tool_id | `foreignId` | constrained('production_resource_tools'), cascadeOnDelete, name('poa_prt_fk') |
| routing_operation_id | `foreignId` | nullable, constrained('routing_operations'), nullOnDelete, name('poa_routing_op_fk') |
| work_order_id | `foreignId` | nullable, constrained('work_orders'), nullOnDelete, name('poa_wo_fk') |
| usage_type | `enum(['required', 'optional'])` | default('required') |
| quantity_required | `unsignedInteger` | default(1) |
| assigned_at | `dateTime` | nullable |
| released_at | `dateTime` | nullable |
| status | `enum(['planned', 'assigned', 'in_use', 'released'])` | default('planned') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['production_resource_tool_id'], 'poa_prt_idx')`
- `$table->index(['work_order_id'], 'poa_wo_idx')`

### capa_8d

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| capa_number | `string(30)` | unique |
| title | `string(200)` |  |
| d0_emergency_response | `text` | nullable |
| d0_date | `date` | nullable |
| d1_team_members | `json` | nullable |
| d1_champion_id | `foreignId` | nullable, constrained('users'), nullOnDelete |
| d2_problem_description | `text` | nullable |
| d2_is_is_not | `text` | nullable |
| d3_containment_actions | `text` | nullable |
| d3_implemented_date | `date` | nullable |
| d3_verified | `boolean` | default(false) |
| d4_root_cause | `text` | nullable |
| d4_escape_point | `text` | nullable |
| d5_corrective_actions | `text` | nullable |
| d6_implementation_plan | `text` | nullable |
| d6_target_date | `date` | nullable |
| d6_completed_date | `date` | nullable |
| d6_verified | `boolean` | default(false) |
| d7_systemic_preventions | `text` | nullable |
| d7_lessons_learned | `text` | nullable |
| d8_recognition | `text` | nullable |
| d8_closure_date | `date` | nullable |
| status | `enum(['d0_open', 'd1_team', 'd2_problem', 'd3_containment', 'd4_root_cause', 'd5_actions', 'd6_implemented', 'd7_prevention', 'd8_closed', ])` | default('d0_open') |
| source_complaint_id | `unsignedBigInteger` | nullable |
| source_type | `string(50)` | nullable |
| created_by | `foreignId` | constrained('users'), restrictOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'status'])`

### dynamic_modification_rules

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| rule_code | `string(20)` |  |
| name | `string(100)` |  |
| description | `text` | nullable |
| tighten_consecutive_fails | `unsignedSmallInteger` | default(2) |
| reduce_after_consecutive_pass | `unsignedSmallInteger` | default(5) |
| skip_after_reduced_pass | `unsignedSmallInteger` | default(10) |
| reinstate_after_tightened_fail | `unsignedSmallInteger` | default(5) |
| is_active | `boolean` | default(true) |
| created_by | `foreignId` | constrained('users'), restrictOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'rule_code'])`

### inspection_stage_logs

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| rule_id | `foreignId` | constrained('dynamic_modification_rules'), restrictOnDelete |
| product_id | `unsignedBigInteger` | nullable |
| supplier_id | `unsignedBigInteger` | nullable |
| current_stage | `enum(['tightened', 'normal', 'reduced', 'skip'])` | default('normal') |
| consecutive_pass | `unsignedSmallInteger` | default(0) |
| consecutive_fail | `unsignedSmallInteger` | default(0) |
| last_evaluated_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'product_id', 'supplier_id'], 'inspection_stage_logs_org_product_supplier_idx')`

### scheduling_boards

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| name | `string` |  |
| horizon_days | `unsignedInteger` | default(14) |
| work_center_ids | `json` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

### scrap_reports

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| work_order_id | `foreignId` | nullable, constrained('work_orders'), nullOnDelete, name('scr_wo_fk') |
| product_id | `foreignId` | constrained('products'), cascadeOnDelete, name('scr_product_fk') |
| warehouse_id | `foreignId` | nullable, constrained('warehouses'), nullOnDelete, name('scr_warehouse_fk') |
| scrap_date | `date` |  |
| scrap_quantity | `decimal(18, 4)` |  |
| unit_of_measure | `string(20)` | nullable |
| scrap_cause | `enum(['defect', 'damage', 'obsolete', 'process_loss', 'machine_failure', 'other'])` | default('defect') |
| scrap_code | `string(30)` | nullable |
| description | `text` | nullable |
| estimated_value | `decimal(18, 4)` | default(0) |
| is_recoverable | `boolean` | default(false) |
| recovery_value | `decimal(18, 4)` | default(0) |
| gl_posted | `boolean` | default(false) |
| gl_posted_at | `dateTime` | nullable |
| reported_by | `foreignId` | nullable, constrained('users'), nullOnDelete, name('scr_reported_by_fk') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'scrap_date'], 'scr_org_date_idx')`
- `$table->index(['work_order_id'], 'scr_wo_idx')`
- `$table->index(['product_id'], 'scr_product_idx')`

### skip_lot_sampling_plans

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| plan_code | `string(30)` |  |
| plan_name | `string(100)` |  |
| plan_type | `enum(['skip_lot', 'reduced', 'normal', 'tightened'])` | default('skip_lot') |
| inspection_frequency | `unsignedTinyInteger` | default(1) |
| sample_size_percent | `decimal(5, 2)` | default(100) |
| accept_number | `unsignedSmallInteger` | default(0) |
| reject_number | `unsignedSmallInteger` | default(1) |
| switch_rule_reduced_to_normal | `unsignedTinyInteger` | nullable |
| switch_rule_normal_to_tightened | `unsignedTinyInteger` | nullable |
| switch_rule_tightened_to_rejected | `unsignedTinyInteger` | nullable |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'plan_code'], 'slsp_org_code_unq')`

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')`

### spc_charts

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| product_id | `unsignedBigInteger` | nullable |
| characteristic_name | `string(100)` |  |
| chart_type | `string(20)` | default('xbar_r'), comment('xbar_r, individual_mr, p_chart, c_chart') |
| subgroup_size | `unsignedTinyInteger` | default(5) |
| ucl | `decimal(15, 6)` | nullable, comment('Upper Control Limit') |
| lcl | `decimal(15, 6)` | nullable, comment('Lower Control Limit') |
| center_line | `decimal(15, 6)` | nullable, comment('Process mean (X-bar-bar)') |
| usl | `decimal(15, 6)` | nullable, comment('Upper Specification Limit') |
| lsl | `decimal(15, 6)` | nullable, comment('Lower Specification Limit') |
| cpk | `decimal(8, 4)` | nullable, comment('Latest computed Cpk') |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'product_id'])`

### spc_subgroups

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| spc_chart_id | `foreignId` | constrained('spc_charts'), cascadeOnDelete |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| measured_at | `timestamp` |  |
| measurements | `json` | comment('Array of measured values') |
| subgroup_mean | `decimal(15, 6)` | nullable |
| subgroup_range | `decimal(15, 6)` | nullable |
| out_of_control | `boolean` | default(false) |
| violated_rules | `json` | nullable, comment('List of violated Western Electric rules') |
| recorded_by | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['spc_chart_id', 'measured_at'])`

### work_centers

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| code | `string` |  |
| name | `string` |  |
| description | `text` | nullable |
| work_center_type | `enum(['machine', 'labor', 'assembly', 'inspection', 'other'])` | default('machine') |
| capacity_per_day | `decimal(8, 2)` | default(8), comment('hours') |
| efficiency_percent | `decimal(5, 2)` | default(100) |
| calendar_type | `enum(['5day', '6day', '7day'])` | default('5day') |
| cost_per_hour | `decimal(10, 2)` | nullable |
| currency_code | `string(3)` | default('SAR') |
| is_active | `boolean` | default(true) |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| deleted_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'code'])`

### capacity_loads

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| work_center_id | `foreignId` | constrained('work_centers'), cascadeOnDelete |
| load_date | `date` |  |
| planned_hours | `decimal(8, 2)` | default(0) |
| actual_hours | `decimal(8, 2)` | default(0) |
| available_hours | `decimal(8, 2)` | default(8) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['work_center_id', 'load_date'])`

### planning_capacity_requirements

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| planning_simulation_id | `foreignId` | constrained('planning_simulations'), cascadeOnDelete |
| work_center_id | `foreignId` | constrained('work_centers'), cascadeOnDelete |
| calendar_date | `date` |  |
| required_hours | `decimal(10, 4)` |  |
| available_hours | `decimal(10, 4)` |  |
| utilization_percentage | `decimal(6, 2)` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['work_center_id', 'calendar_date'], 'ltp_cap_wc_date_idx')`

### mrp_capacity_requirements

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| mrp_run_id | `unsignedBigInteger` | nullable, comment('FK to mrp_runs if present') |
| work_center_id | `foreignId` | constrained('work_centers'), cascadeOnDelete |
| planned_order_id | `unsignedBigInteger` | nullable, comment('Links to mrp_planned_orders') |
| required_date | `date` |  |
| required_hours | `decimal(10, 2)` |  |
| available_hours | `decimal(10, 2)` |  |
| load_pct | `decimal(6, 2)` | comment('required_hours / available_hours * 100') |
| status | `enum(['feasible', 'overloaded'])` | default('feasible') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'work_center_id', 'required_date'], 'mrp_capacity_requirements_org_work_center_req_date_idx')`
- `$table->index(['mrp_run_id'])`

### production_lines

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| code | `string(30)` |  |
| name | `string` |  |
| work_center_id | `foreignId` | nullable, constrained('work_centers'), nullOnDelete |
| capacity_per_hour | `decimal(10, 4)` | nullable |
| unit_id | `foreignId` | nullable, constrained('units_of_measure'), nullOnDelete |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'code'], 'pl_org_code_unique')`

### work_center_capacities

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| work_center_id | `foreignId` | constrained('work_centers'), cascadeOnDelete |
| valid_from | `date` |  |
| valid_to | `date` | nullable |
| available_hours_per_day | `decimal(8, 2)` | comment('Raw capacity in hours per working day') |
| days_per_week | `unsignedTinyInteger` | default(5), comment('Number of working days per week (1-7)') |
| efficiency_pct | `decimal(5, 2)` | default(100.00), comment('Percentage of time that is actually productive') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'work_center_id', 'valid_from'], 'work_center_capacities_org_work_center_valid_from_idx')`

### work_center_exceptions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| work_center_id | `foreignId` | constrained('work_centers'), cascadeOnDelete |
| exception_date | `date` |  |
| available_hours | `decimal(5, 2)` | default(0) |
| reason | `string` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['work_center_id', 'exception_date'])`

### work_order_co_product_actuals

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete, name('wocpa_org_fk') |
| work_order_id | `foreignId` | constrained('work_orders'), cascadeOnDelete, name('wocpa_wo_fk') |
| bom_co_product_id | `foreignId` | nullable, constrained('bom_co_products'), nullOnDelete, name('wocpa_bcp_fk') |
| product_id | `foreignId` | constrained('products'), cascadeOnDelete, name('wocpa_product_fk') |
| co_product_type | `enum(['co_product', 'by_product', 'scrap'])` | default('co_product') |
| planned_quantity | `decimal(18, 4)` | default(0) |
| actual_quantity | `decimal(18, 4)` | default(0) |
| unit_of_measure | `string(20)` | nullable |
| warehouse_id | `foreignId` | nullable, constrained('warehouses'), nullOnDelete, name('wocpa_warehouse_fk') |
| posted_to_stock | `boolean` | default(false) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['work_order_id'], 'wocpa_wo_idx')`

### audit_findings

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| audit_plan_id | `unsignedBigInteger` |  |
| finding_number | `string(30)` |  |
| finding_type | `enum(['major_nc', 'minor_nc', 'observation', 'positive'])` | default('minor_nc') |
| description | `text` |  |
| requirement_reference | `text` | nullable |
| evidence | `text` | nullable |
| status | `enum(['open', 'in_progress', 'closed', 'verified'])` | default('open') |
| due_date | `date` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('audit_plan_id', 'audit_finding_plan_fk')->references('id')->on('audit_plans')->cascadeOnDelete()`

### task_list_operations

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| task_list_id | `unsignedBigInteger` |  |
| operation_number | `unsignedSmallInteger` |  |
| description | `string` |  |
| work_center_id | `unsignedBigInteger` | nullable |
| planned_hours | `decimal(6, 2)` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('task_list_id', 'pm_tl_op_tl_fk')->references('id')->on('maintenance_task_lists')->cascadeOnDelete()`
- `$table->foreign('work_center_id', 'pm_tl_op_wc_fk')->references('id')->on('work_centers')->nullOnDelete()`

## 0220_messaging.php

### conversations

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| subject | `string` | nullable |
| type | `string(20)` | default('direct') — direct, group |
| created_by | `foreignId` | constrained('users'), cascadeOnDelete |
| last_message_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'last_message_at'])`

### conversation_messages

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| conversation_id | `foreignId` | constrained, cascadeOnDelete |
| sender_id | `foreignId` | constrained('users'), cascadeOnDelete |
| content | `text` |  |
| type | `string(20)` | default('text') — text, file, image |
| file_url | `string` | nullable |
| file_name | `string` | nullable |
| file_size | `unsignedInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['conversation_id', 'created_at'])`

### message_templates

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| name | `string` |  |
| code | `string(50)` | invoice_sent, payment_received, order_confirmed |
| channel_type | `string(30)` | email, sms, whatsapp, push_notification |
| category | `string(50)` | transactional, promotional, reminder, notification |
| subject | `string` | nullable — For email |
| body | `text` | Supports {{variable}} placeholders |
| html_body | `text` | nullable — Rich HTML for email |
| variables | `json` | nullable — Available placeholders |
| attachments_config | `json` | nullable — Auto-attach invoice PDF, etc. |
| language | `string(5)` | default('en') |
| parent_template_id | `foreignId` | nullable, constrained('message_templates'), nullOnDelete |
| is_system | `boolean` | default(false) — System template, cannot be deleted |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'code', 'channel_type', 'language'], 'msg_tpl_org_code_channel_lang_unique')`

### channel_template_approvals

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| template_id | `foreignId` | constrained('message_templates'), cascadeOnDelete |
| channel_type | `string(30)` |  |
| provider_template_id | `string` | nullable — WhatsApp business template ID |
| status | `string(20)` | default('pending') — pending, approved, rejected |
| rejection_reason | `text` | nullable |
| submitted_at | `timestamp` | nullable |
| approved_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['template_id', 'channel_type'])`

### messaging_channels

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| channel_type | `string(30)` | email, sms, whatsapp, push_notification |
| name | `string` |  |
| provider | `string(50)` | smtp, sendgrid, twilio, vonage, firebase, whatsapp_business |
| credentials | `text` |  |
| settings | `json` | nullable — Rate limits, sender defaults, etc. |
| sender_name | `string` | nullable |
| sender_address | `string` | nullable — Email, phone number |
| is_default | `boolean` | default(false) |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'channel_type', 'is_default'], 'msg_channels_org_type_default_unique')`
- `$table->index(['organization_id', 'is_active'])`

Added by later migrations:

- `0540_messaging_channel_default_marker.php`: `$table->dropUnique('msg_channels_org_type_default_unique')`
- `0540_messaging_channel_default_marker.php`: `$table->string('default_for_type', 30)->nullable()->after('sender_address')`
- `0540_messaging_channel_default_marker.php`: `$table->dropColumn('is_default')`
- `0540_messaging_channel_default_marker.php`: `$table->unique(['organization_id', 'default_for_type'], 'msg_channels_org_default_unique')`

### messaging_automations

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| name | `string` |  |
| description | `text` | nullable |
| trigger_event | `string(100)` | invoice.created, payment.received, order.shipped, customer.birthday, invoice.overdue |
| trigger_entity | `string(50)` | nullable — invoice, payment, order, customer |
| timing | `string(20)` | default('immediate') — immediate, delayed, scheduled |
| delay_minutes | `unsignedInteger` | default(0) — For delayed triggers |
| delay_unit | `string(10)` | nullable — minutes, hours, days |
| conditions | `json` | nullable — Filter conditions (amount > 500, status = overdue, etc.) |
| channel_type | `string(30)` | email, sms, whatsapp, push_notification |
| template_id | `foreignId` | constrained('message_templates'), cascadeOnDelete |
| channel_id | `foreignId` | nullable, constrained('messaging_channels'), nullOnDelete |
| recipient_type | `string(30)` | default('contact') — contact, user, custom, role |
| recipient_config | `json` | nullable — CC, BCC, additional recipients |
| max_sends_per_contact | `unsignedInteger` | nullable — Per day/week/month |
| rate_limit_period | `string(10)` | nullable — day, week, month |
| is_active | `boolean` | default(true) |
| execution_count | `unsignedInteger` | default(0) |
| last_executed_at | `timestamp` | nullable |
| created_by | `foreignId` | constrained('users'), cascadeOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'trigger_event', 'is_active'], 'msg_auto_org_trigger_active_idx')`

### message_campaign_recipients

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| campaign_id | `foreignId` | nullable, constrained('messaging_automations'), nullOnDelete |
| recipient_type | `string(30)` | contact, user, custom |
| recipient_id | `unsignedBigInteger` | nullable — polymorphic reference |
| email | `string` | nullable |
| phone | `string` | nullable |
| name | `string` | nullable |
| status | `string(20)` | default('pending') — pending, sent, failed, unsubscribed |
| failure_reason | `text` | nullable |
| sent_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['campaign_id', 'status'])`
- `$table->index(['organization_id', 'recipient_type', 'recipient_id'], 'mcr_org_recipient_idx')`

## 0240_purchase.php

### ers_runs

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| run_date | `date` |  |
| status | `enum(['running', 'completed', 'failed'])` | default('running') |
| processed_count | `unsignedInteger` | default(0) |
| error_count | `unsignedInteger` | default(0) |
| run_by | `unsignedBigInteger` | nullable |
| completed_at | `dateTime` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id', 'ers_run_org_fk')->references('id')->on('organizations')`
- `$table->foreign('run_by', 'ers_run_by_fk')->references('id')->on('users')`

### pcards

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| card_number_masked | `string(20)` |  |
| card_holder_id | `unsignedBigInteger` |  |
| cost_center_id | `unsignedBigInteger` | nullable |
| credit_limit | `decimal(18, 4)` |  |
| currency | `char(3)` | default('SAR') |
| valid_from | `date` |  |
| valid_to | `date` |  |
| status | `enum(['active', 'suspended', 'cancelled'])` | default('active') |
| single_transaction_limit | `decimal(18, 4)` | nullable |
| monthly_limit | `decimal(18, 4)` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')`
- `$table->foreign('card_holder_id', 'pcard_holder_fk')->references('id')->on('users')`
- `$table->foreign('cost_center_id', 'pcard_cc_fk')->references('id')->on('cost_centers')->nullOnDelete()`

### pcard_statements

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| pcard_id | `unsignedBigInteger` |  |
| statement_period_start | `date` |  |
| statement_period_end | `date` |  |
| total_amount | `decimal(18, 4)` |  |
| currency | `char(3)` | default('SAR') |
| status | `enum(['uploaded', 'reconciled', 'posted'])` | default('uploaded') |
| uploaded_by | `unsignedBigInteger` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')`
- `$table->foreign('pcard_id', 'pcard_stmt_card_fk')->references('id')->on('pcards')`
- `$table->foreign('uploaded_by', 'pcard_stmt_usr_fk')->references('id')->on('users')`

### pcard_transactions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| pcard_statement_id | `unsignedBigInteger` |  |
| transaction_date | `date` |  |
| merchant_name | `string` |  |
| merchant_category_code | `string(10)` | nullable |
| amount | `decimal(18, 4)` |  |
| currency | `char(3)` | default('SAR') |
| gl_account_id | `unsignedBigInteger` | nullable |
| cost_center_id | `unsignedBigInteger` | nullable |
| status | `enum(['unreconciled', 'reconciled', 'disputed'])` | default('unreconciled') |
| receipt_attached | `boolean` | default(false) |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')`
- `$table->foreign('pcard_statement_id', 'pcard_txn_stmt_fk')->references('id')->on('pcard_statements')->cascadeOnDelete()`
- `$table->foreign('gl_account_id', 'pcard_txn_gl_fk')->references('id')->on('chart_of_accounts')->nullOnDelete()`
- `$table->foreign('cost_center_id', 'pcard_txn_cc_fk')->references('id')->on('cost_centers')->nullOnDelete()`

### purchase_requisitions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string(36)` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| requisition_number | `string(30)` |  |
| requisition_date | `date` |  |
| required_by_date | `date` | nullable |
| requisition_type | `enum(['standard', 'subcontracting', 'consignment', 'stock_transfer'])` | default('standard') |
| status | `enum(['draft', 'pending_approval', 'approved', 'converted_to_po', 'cancelled'])` | default('draft') |
| requested_by | `foreignId` | constrained('users') |
| approved_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| approved_at | `timestamp` | nullable |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'requisition_number'], 'pr_org_num_unique')`
- `$table->index(['organization_id', 'status'], 'pr_org_status_idx')`

### release_strategies

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| name | `string(255)` |  |
| description | `text` | nullable |
| document_type | `enum(['purchase_order', 'purchase_requisition'])` |  |
| is_active | `boolean` | default(true) |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'document_type', 'is_active'])`

### release_strategy_levels

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| release_strategy_id | `foreignId` | constrained('release_strategies'), cascadeOnDelete |
| level | `unsignedTinyInteger` |  |
| role | `string(100)` |  |
| min_amount | `decimal(15, 4)` | nullable |
| max_amount | `decimal(15, 4)` | nullable |
| label | `string(100)` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['release_strategy_id', 'level'])`
- `$table->index(['organization_id', 'release_strategy_id'], 'release_strategy_levels_org_strategy_idx')`

### release_strategy_approvals

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| release_strategy_id | `foreignId` | constrained('release_strategies'), cascadeOnDelete |
| level_id | `foreignId` | constrained('release_strategy_levels'), cascadeOnDelete |
| document_type | `enum(['purchase_order', 'purchase_requisition'])` |  |
| document_id | `unsignedBigInteger` |  |
| status | `enum(['pending', 'approved', 'rejected'])` | default('pending') |
| approver_id | `foreignId` | nullable, constrained('users'), nullOnDelete |
| comments | `text` | nullable |
| acted_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'document_type', 'document_id'], 'release_strategy_approvals_org_doc_type_doc_id_idx')`
- `$table->index(['organization_id', 'status'])`

### rfq_headers

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| rfq_number | `string(30)` |  |
| title | `string(200)` |  |
| status | `enum(['draft', 'sent', 'closed', 'cancelled', 'awarded'])` | default('draft') |
| submission_deadline | `date` | nullable |
| delivery_date | `date` | nullable |
| delivery_address | `text` | nullable |
| currency_code | `string(3)` | default('SAR') |
| notes | `text` | nullable |
| created_by | `unsignedBigInteger` |  |
| branch_id | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'rfq_number'], 'rfq_headers_org_number_unique')`
- `$table->index(['organization_id', 'status'], 'rfq_headers_org_status_idx')`

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade')`
- `$table->foreign('created_by')->references('id')->on('users')`
- `$table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete()`

### supplier_evaluation_criteria

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| name | `string` |  |
| description | `text` | nullable |
| category | `enum(['quality', 'delivery', 'price', 'service', 'compliance'])` | default('quality') |
| weight_percent | `decimal(5, 2)` | default(20) |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('organization_id')`

## 0250_realestate.php

### occupancy_snapshots

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `unsignedBigInteger` |  |
| snapshot_type | `string` | building\|property\|portfolio |
| reference_id | `unsignedBigInteger` | building_id\|property_id\|portfolio_id |
| snapshot_date | `date` |  |
| total_units | `unsignedInteger` |  |
| occupied_units | `unsignedInteger` |  |
| vacant_units | `unsignedInteger` |  |
| occupancy_rate | `decimal(5, 2)` | percentage 0–100 |
| total_area_sqm | `decimal(15, 4)` | default(0) |
| occupied_area_sqm | `decimal(15, 4)` | default(0) |
| area_occupancy_rate | `decimal(5, 2)` | default(0) |
| potential_rent | `decimal(15, 2)` | default(0) — sum of market rent of all units |
| actual_rent | `decimal(15, 2)` | default(0) — sum of contract rent for occupied units |
| currency | `string(3)` | default('SAR') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'snapshot_type', 'reference_id', 'snapshot_date'], 're_occ_snap_org_type_ref_date_unique')`
- `$table->index(['organization_id', 'snapshot_type', 'reference_id'], 're_occ_snap_org_type_ref_idx')`

### portfolios

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| code | `string(30)` |  |
| name | `string(200)` |  |
| type | `string(30)` | default('commercial') |
| currency_code | `string(5)` | default('SAR') |
| description | `text` | nullable |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'code'], 'portfolios_org_code_uniq')`
- `$table->index(['organization_id', 'is_active'], 'portfolios_org_active_idx')`

### posting_runs

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| run_number | `string(50)` |  |
| type | `string(30)` | default('rent') |
| posting_date | `date` |  |
| period_year | `integer` |  |
| period_month | `unsignedTinyInteger` |  |
| status | `string(20)` | default('draft') |
| contracts_processed | `unsignedInteger` | default(0) |
| total_amount | `decimal(18, 4)` | default(0) |
| currency_code | `string(5)` | default('SAR') |
| executed_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| executed_at | `dateTime` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'run_number'], 'posting_runs_org_num_uniq')`
- `$table->index(['organization_id', 'type', 'period_year', 'period_month'], 'posting_runs_org_type_period_idx')`

### properties

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| portfolio_id | `foreignId` | constrained('portfolios'), cascadeOnDelete |
| code | `string(30)` |  |
| name | `string(200)` |  |
| type | `string(30)` | default('commercial') — commercial\|residential\|industrial\|mixed |
| street_address | `string(500)` | nullable |
| city | `string(100)` | nullable |
| state_province | `string(100)` | nullable |
| postal_code | `string(20)` | nullable |
| country_code | `string(5)` | nullable |
| total_area_sqm | `decimal(14, 4)` | default(0) |
| land_area_sqm | `decimal(14, 4)` | nullable |
| current_valuation | `decimal(18, 4)` | nullable |
| valuation_currency | `string(5)` | default('SAR') |
| valuation_date | `date` | nullable |
| ownership_type | `string(30)` | default('owned') — owned\|leased_in\|managed |
| status | `string(20)` | default('active') — active\|inactive\|under_development\|disposed |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'code'], 'properties_org_code_uniq')`
- `$table->index(['organization_id', 'portfolio_id', 'status'], 'properties_org_portfolio_status_idx')`

### buildings

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| property_id | `foreignId` | constrained('properties'), cascadeOnDelete |
| code | `string(30)` |  |
| name | `string(200)` |  |
| floors_above_ground | `unsignedSmallInteger` | default(1) |
| floors_below_ground | `unsignedSmallInteger` | default(0) |
| gross_area_sqm | `decimal(14, 4)` | default(0) |
| net_lettable_area_sqm | `decimal(14, 4)` | default(0) — NLA |
| year_built | `year` | nullable |
| construction_type | `string(50)` | nullable — concrete\|steel\|wood\|etc |
| status | `string(20)` | default('active') — active\|under_renovation\|demolished |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'property_id', 'code'], 'buildings_org_property_code_uniq')`
- `$table->index(['organization_id', 'property_id'], 'buildings_org_property_idx')`

### floors

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| building_id | `foreignId` | constrained('buildings'), cascadeOnDelete |
| floor_number | `smallInteger` | negative = basement |
| floor_label | `string(50)` | nullable — "Ground Floor", "Mezzanine", "B1" |
| total_area_sqm | `decimal(14, 4)` | default(0) |
| lettable_area_sqm | `decimal(14, 4)` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['building_id', 'floor_number'], 'floors_building_num_uniq')`

### rental_units

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| building_id | `foreignId` | constrained('buildings'), cascadeOnDelete |
| floor_id | `foreignId` | nullable, constrained('floors'), nullOnDelete |
| code | `string(50)` |  |
| name | `string(200)` | nullable |
| unit_type | `string(30)` | default('office') |
| area_sqm | `decimal(14, 4)` | default(0) |
| status | `string(20)` | default('vacant') |
| usage_type | `string(50)` | nullable — sub-classification |
| rooms | `unsignedTinyInteger` | nullable |
| bathrooms | `unsignedTinyInteger` | nullable |
| has_parking | `boolean` | default(false) |
| is_active | `boolean` | default(true) |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'building_id', 'code'], 'rental_units_org_building_code_uniq')`
- `$table->index(['organization_id', 'status'], 'rental_units_org_status_idx')`
- `$table->index(['organization_id', 'building_id', 'status'], 'rental_units_org_building_status_idx')`

### rental_contracts

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| contract_number | `string(50)` |  |
| contract_type | `string(20)` | default('lease_out') |
| rental_unit_id | `foreignId` | constrained('rental_units'), cascadeOnDelete |
| counterparty_type | `string(30)` | nullable — contact\|vendor\|other |
| counterparty_id | `unsignedBigInteger` | nullable |
| counterparty_name | `string(200)` | nullable — denormalized for display |
| start_date | `date` |  |
| end_date | `date` | nullable — null = indefinite |
| notice_date | `date` | nullable — required notice for termination |
| notice_period_months | `unsignedSmallInteger` | default(1) |
| status | `string(20)` | default('draft') |
| currency_code | `string(5)` | default('SAR') |
| payment_day | `unsignedSmallInteger` | default(1) — day of month rent is due |
| payment_frequency | `string(20)` | default('monthly') |
| auto_renew | `boolean` | default(false) |
| auto_renew_months | `unsignedSmallInteger` | nullable |
| notes | `text` | nullable |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |
| ibr_percent | `decimal(8, 4)` | nullable, comment('Incremental Borrowing Rate used for PV calculation (annual %)') |
| rou_asset_amount | `decimal(20, 4)` | nullable, comment('Right-of-Use asset value at commencement (= initial lease liability)') |
| lease_liability_amount | `decimal(20, 4)` | nullable, comment('Remaining lease liability (updated each period)') |
| ifrs16_commencement_date | `date` | nullable, comment('Date on which IFRS 16 recognition started') |
| ifrs16_applied | `boolean` | default(false) |

Indexes:

- `$table->unique(['organization_id', 'contract_number'], 'rental_contracts_org_num_uniq')`
- `$table->index(['organization_id', 'status', 'end_date'], 'rental_contracts_org_status_end_idx')`
- `$table->index(['organization_id', 'rental_unit_id'], 'rental_contracts_org_unit_idx')`

### contract_conditions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| contract_id | `foreignId` | constrained('rental_contracts'), cascadeOnDelete |
| condition_type | `string(30)` | default('base_rent') |
| description | `string(200)` | nullable |
| amount | `decimal(18, 4)` |  |
| basis | `string(20)` | default('flat') |
| valid_from | `date` |  |
| valid_to | `date` | nullable |
| escalation_type | `string(20)` | nullable |
| escalation_rate | `decimal(8, 4)` | nullable — pct for fixed_pct |
| escalation_index | `string(50)` | nullable — CPI index name |
| escalation_frequency | `string(20)` | nullable — annual\|biennial |
| next_escalation_date | `date` | nullable |
| is_taxable | `boolean` | default(true) |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['contract_id', 'condition_type', 'is_active'], 'contract_conditions_contract_type_active_idx')`
- `$table->index(['next_escalation_date', 'is_active'], 'contract_conditions_escalation_due_idx')`

### contract_options

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| contract_id | `foreignId` | constrained('rental_contracts'), cascadeOnDelete |
| option_type | `string(30)` | default('renewal') |
| exercise_window_start | `date` | nullable |
| exercise_window_end | `date` | nullable |
| exercise_deadline | `date` | latest date to exercise |
| new_term_months | `unsignedSmallInteger` | nullable — for renewal options |
| new_rent_amount | `decimal(18, 4)` | nullable — fixed rent if exercised |
| status | `string(20)` | default('pending') |
| exercised_at | `date` | nullable |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['contract_id', 'status'], 'contract_options_contract_status_idx')`
- `$table->index(['exercise_deadline', 'status'], 'contract_options_deadline_status_idx')`

### ifrs16_schedules

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| contract_id | `unsignedBigInteger` |  |
| period_date | `date` | comment('First day of the accounting period') |
| opening_liability | `decimal(20, 4)` |  |
| interest_expense | `decimal(20, 4)` | comment('Opening liability × monthly IBR') |
| lease_payment | `decimal(20, 4)` | comment('Contractual rent payment') |
| principal_reduction | `decimal(20, 4)` | comment('Payment minus interest') |
| closing_liability | `decimal(20, 4)` |  |
| rou_depreciation | `decimal(20, 4)` | comment('Straight-line depreciation of ROU asset') |
| rou_book_value | `decimal(20, 4)` | comment('ROU asset net book value at end of period') |
| gl_posted | `boolean` | default(false) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['contract_id', 'period_date'])`
- `$table->index(['contract_id', 'gl_posted'])`

Foreign keys:

- `$table->foreign('contract_id')->references('id')->on('rental_contracts')->cascadeOnDelete()`

### posting_run_items

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| posting_run_id | `foreignId` | constrained('posting_runs'), cascadeOnDelete |
| contract_id | `foreignId` | constrained('rental_contracts'), cascadeOnDelete |
| condition_id | `foreignId` | constrained('contract_conditions'), cascadeOnDelete |
| condition_type | `string(30)` |  |
| amount | `decimal(18, 4)` |  |
| tax_amount | `decimal(18, 4)` | default(0) |
| total_amount | `decimal(18, 4)` |  |
| status | `string(20)` | default('pending') — pending\|posted\|skipped\|error |
| error_message | `string(500)` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['posting_run_id', 'status'], 'posting_run_items_run_status_idx')`
- `$table->index(['contract_id'], 'posting_run_items_contract_idx')`

### security_deposits

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| contract_id | `foreignId` | constrained('rental_contracts'), cascadeOnDelete |
| deposit_number | `string(50)` |  |
| required_amount | `decimal(18, 4)` |  |
| collected_amount | `decimal(18, 4)` | default(0) |
| currency_code | `string(5)` | default('SAR') |
| collected_date | `date` | nullable |
| interest_rate_pct | `decimal(8, 4)` | default(0) — annual interest on deposit |
| accrued_interest | `decimal(18, 4)` | default(0) |
| status | `string(20)` | default('pending') |
| refunded_amount | `decimal(18, 4)` | default(0) |
| refund_date | `date` | nullable |
| refund_reason | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'deposit_number'], 'security_deposits_org_num_uniq')`
- `$table->index(['organization_id', 'contract_id'], 'security_deposits_org_contract_idx')`

### service_charge_settlements

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| settlement_number | `string(50)` |  |
| property_id | `foreignId` | constrained('properties'), cascadeOnDelete |
| settlement_year | `integer` |  |
| status | `string(20)` | default('draft') |
| total_actual_costs | `decimal(18, 4)` | default(0) |
| total_billed_to_tenants | `decimal(18, 4)` | default(0) |
| total_adjustment | `decimal(18, 4)` | default(0) — positive = tenant owes, negative = refund |
| currency_code | `string(5)` | default('SAR') |
| settlement_date | `date` | nullable |
| approved_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| approved_at | `dateTime` | nullable |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'settlement_number'], 'service_charge_settlements_org_num_uniq')`
- `$table->index(['organization_id', 'property_id', 'settlement_year'], 'service_charge_settlements_org_property_year_idx')`

### service_charge_allocations

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| settlement_id | `foreignId` | constrained('service_charge_settlements'), cascadeOnDelete |
| contract_id | `foreignId` | constrained('rental_contracts'), cascadeOnDelete |
| unit_area_sqm | `decimal(14, 4)` | default(0) |
| allocation_pct | `decimal(8, 4)` | default(0) |
| actual_amount | `decimal(18, 4)` | default(0) — tenant's share of actual costs |
| billed_amount | `decimal(18, 4)` | default(0) — what tenant already paid on account |
| adjustment_amount | `decimal(18, 4)` | default(0) — positive = additional charge, negative = refund |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['settlement_id', 'contract_id'], 'service_charge_allocations_settlement_contract_uniq')`

### service_charge_items

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| settlement_id | `foreignId` | constrained('service_charge_settlements'), cascadeOnDelete |
| cost_category | `string(100)` | electricity\|water\|cleaning\|security\|maintenance\|insurance\|etc |
| actual_cost | `decimal(18, 4)` | default(0) |
| lettable_area_sqm | `decimal(14, 4)` | default(0) — total apportionable area |
| cost_per_sqm | `decimal(14, 6)` | default(0) — computed |
| allocation_basis | `string(30)` | default('area') |
| description | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['settlement_id'], 'service_charge_items_settlement_idx')`

### vacancy_periods

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| rental_unit_id | `unsignedBigInteger` |  |
| building_id | `unsignedBigInteger` |  |
| property_id | `unsignedBigInteger` | nullable |
| portfolio_id | `unsignedBigInteger` | nullable |
| vacant_from | `date` |  |
| vacant_to | `date` | nullable — null = still vacant |
| vacancy_reason | `string` | nullable — lease_expired\|early_termination\|new_unit\|renovation\|owner_use |
| market_rent | `decimal(15, 2)` | nullable — expected rent while vacant (for revenue loss calc) |
| currency | `string(3)` | default('SAR') |
| vacancy_loss | `decimal(15, 2)` | nullable — computed: days_vacant × daily_market_rent |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'rental_unit_id'])`
- `$table->index(['organization_id', 'building_id'])`
- `$table->index(['vacant_from', 'vacant_to'])`

## 0260_sales.php

### backdated_transactions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| transaction_type | `string` |  |
| transaction_id | `unsignedBigInteger` |  |
| transaction_date | `date` | The backdated date used |
| entry_date | `date` | Actual date of entry |
| reason | `string` | nullable — Reason for backdating |
| approved_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| approved_at | `timestamp` | nullable |
| created_by | `foreignId` | constrained('users'), cascadeOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `morphs('transaction')`
- `$table->index(['organization_id', 'transaction_date'])`

### backorder_records

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| sales_order_id | `foreignId` | constrained('sales_orders'), cascadeOnDelete, name('bor_so_fk') |
| sales_order_line_id | `foreignId` | nullable, constrained('sales_order_lines'), nullOnDelete, name('bor_sol_fk') |
| product_id | `foreignId` | constrained('products'), cascadeOnDelete, name('bor_product_fk') |
| original_quantity | `decimal(18, 4)` |  |
| backordered_quantity | `decimal(18, 4)` |  |
| fulfilled_quantity | `decimal(18, 4)` | default(0) |
| status | `enum(['open', 'partially_fulfilled', 'fulfilled', 'cancelled'])` | default('open') |
| original_delivery_date | `date` | nullable |
| rescheduled_delivery_date | `date` | nullable |
| reason | `text` | nullable |
| priority | `unsignedTinyInteger` | default(5) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'status'], 'bor_org_status_idx')`
- `$table->index(['organization_id', 'product_id'], 'bor_org_product_idx')`
- `$table->index(['rescheduled_delivery_date'], 'bor_reschedule_date_idx')`

### billing_plan_items

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete, name('bpi_org_fk') |
| billing_plan_id | `foreignId` | constrained('billing_plans'), cascadeOnDelete, name('bpi_plan_fk') |
| milestone_description | `string(255)` | nullable |
| billing_date | `date` |  |
| billing_percent | `decimal(5, 2)` | nullable |
| billing_amount | `decimal(18, 4)` |  |
| status | `enum(['pending', 'billed', 'cancelled'])` | default('pending') |
| invoice_id | `foreignId` | nullable, constrained('invoices'), nullOnDelete, name('bpi_invoice_fk') |
| billed_at | `dateTime` | nullable |
| sort_order | `unsignedSmallInteger` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['billing_plan_id', 'billing_date'], 'bpi_plan_date_idx')`

### billing_plans

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| sales_order_id | `foreignId` | nullable, constrained('sales_orders'), nullOnDelete, name('bp_so_fk') |
| quotation_id | `foreignId` | nullable, constrained('quotations'), nullOnDelete, name('bp_quot_fk') |
| plan_type | `enum(['milestone', 'periodic'])` | default('milestone') |
| billing_currency | `char(3)` | default('SAR') |
| total_value | `decimal(18, 4)` | default(0) |
| billed_value | `decimal(18, 4)` | default(0) |
| status | `enum(['draft', 'active', 'completed', 'cancelled'])` | default('draft') |
| start_date | `date` | nullable |
| end_date | `date` | nullable |
| periodic_interval_days | `unsignedSmallInteger` | nullable |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'sales_order_id'], 'bp_org_so_idx')`

### bulk_sale_batches

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| branch_id | `foreignId` | nullable, constrained, nullOnDelete |
| batch_number | `string(30)` |  |
| name | `string` | nullable |
| sale_date | `date` | Allows backdating |
| original_sale_date | `date` | nullable — If different from entry date |
| currency_code | `string(3)` | default('SAR') |
| total_invoices | `unsignedInteger` | default(0) |
| total_subtotal | `decimal(15, 2)` | default(0) |
| total_discount | `decimal(15, 2)` | default(0) |
| total_tax | `decimal(15, 2)` | default(0) |
| total_amount | `decimal(15, 2)` | default(0) |
| status | `string(20)` | default('draft') — draft, processing, completed, partially_completed, failed |
| processed_count | `unsignedInteger` | default(0) |
| success_count | `unsignedInteger` | default(0) |
| failed_count | `unsignedInteger` | default(0) |
| errors | `json` | nullable |
| started_at | `timestamp` | nullable |
| completed_at | `timestamp` | nullable |
| auto_post | `boolean` | default(false) |
| auto_send_email | `boolean` | default(false) |
| generate_receipts | `boolean` | default(false) |
| payment_method | `string` | nullable |
| bank_account_id | `foreignId` | nullable, constrained('bank_accounts'), nullOnDelete |
| notes | `text` | nullable |
| created_by | `foreignId` | constrained('users'), cascadeOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'sale_date'])`
- `$table->index(['organization_id', 'status'])`

### commission_masters

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| sales_rep_id | `unsignedBigInteger` |  |
| commission_plan_name | `string` |  |
| effective_from | `date` |  |
| effective_to | `date` | nullable |
| base_rate | `decimal(8, 4)` |  |
| currency | `char(3)` | default('SAR') |
| quota_amount | `decimal(18, 4)` | nullable |
| status | `enum(['active', 'inactive'])` | default('active') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade')`
- `$table->foreign('sales_rep_id', 'comm_master_usr_fk')->references('id')->on('users')->onDelete('cascade')`

### commission_payments

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| payment_reference | `string` | unique |
| sales_rep_id | `unsignedBigInteger` |  |
| period_year | `unsignedSmallInteger` |  |
| period_month | `tinyInteger` |  |
| total_amount | `decimal(18, 4)` |  |
| currency | `char(3)` | default('SAR') |
| payment_date | `date` |  |
| status | `enum(['pending', 'processed'])` | default('pending') |
| payslip_id | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade')`
- `$table->foreign('sales_rep_id', 'comm_pay_usr_fk')->references('id')->on('users')->onDelete('cascade')`
- `$table->foreign('payslip_id', 'comm_pay_payslip_fk')->references('id')->on('payslips')->onDelete('set null')`

### commission_rules

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| commission_master_id | `unsignedBigInteger` |  |
| rule_type | `enum(['flat', 'tiered', 'product_category', 'customer_group'])` |  |
| condition_field | `string` | nullable |
| condition_value | `string` | nullable |
| rate | `decimal(8, 4)` |  |
| tier_from | `decimal(18, 4)` | nullable |
| tier_to | `decimal(18, 4)` | nullable |
| priority | `unsignedSmallInteger` | default(10) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade')`
- `$table->foreign('commission_master_id', 'comm_rule_master_fk')->references('id')->on('commission_masters')->onDelete('cascade')`

### customer_account_groups

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| group_code | `string(4)` | unique |
| description | `string` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade')`

### customer_groups

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| name | `string(100)` |  |
| code | `string(20)` |  |
| description | `text` | nullable |
| default_discount_percent | `decimal(5, 2)` | default(0) |
| credit_limit | `decimal(15, 4)` | nullable |
| payment_terms_days | `unsignedSmallInteger` | default(0) — 0 = cash |
| tax_exempt | `boolean` | default(false) |
| wholesale | `boolean` | default(false) |
| is_active | `boolean` | default(true) |
| priority | `unsignedSmallInteger` | default(0) — Higher = better pricing |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'code'])`

### delivery_modes

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| name | `string` |  |
| code | `string(30)` |  |
| type | `string(30)` | pickup, standard, express, same_day, next_day, freight, digital, custom |
| description | `text` | nullable |
| icon | `string` | nullable |
| pricing_type | `string(20)` | default('flat') — free, flat, weight_based, value_based, distance_based, custom |
| flat_rate | `decimal(15, 2)` | default(0) |
| pricing_rules | `json` | nullable — Complex pricing tiers |
| min_delivery_days | `unsignedSmallInteger` | nullable |
| max_delivery_days | `unsignedSmallInteger` | nullable |
| delivery_time_label | `string` | nullable — "2-3 business days" |
| free_shipping_min | `decimal(15, 2)` | nullable |
| max_weight_kg | `decimal(10, 2)` | nullable |
| max_value | `decimal(15, 2)` | nullable |
| supported_zones | `json` | nullable — Delivery zone IDs |
| excluded_products | `json` | nullable — Product IDs not eligible |
| tracking_enabled | `boolean` | default(false) |
| carrier_provider | `string` | nullable — aramex, dhl, fedex, bluedart |
| carrier_config | `json` | nullable |
| available_days | `json` | nullable — [1,2,3,4,5] Mon-Fri |
| cutoff_time | `string(5)` | nullable — "14:00" for same-day |
| requires_address | `boolean` | default(true) |
| is_active | `boolean` | default(true) |
| display_order | `unsignedSmallInteger` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'code'])`
- `$table->index(['organization_id', 'is_active'])`
- `$table->index(['organization_id', 'type'])`

### delivery_split_rules

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| rule_name | `string(100)` |  |
| split_criteria | `enum(['warehouse', 'delivery_date', 'route', 'weight', 'volume'])` | default('warehouse') |
| applies_to | `enum(['all_customers', 'customer_group', 'specific_customer'])` | default('all_customers') |
| applies_to_id | `unsignedBigInteger` | nullable |
| allow_partial_delivery | `boolean` | default(true) |
| minimum_delivery_quantity_pct | `decimal(5, 2)` | default(0) |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'is_active'], 'delivery_split_rules_org_active_idx')`

### delivery_zones

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| name | `string` |  |
| code | `string(30)` |  |
| countries | `json` | nullable — Country codes |
| states | `json` | nullable — State codes |
| cities | `json` | nullable |
| postal_codes | `json` | nullable — Ranges or specific codes |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'code'])`

### delivery_zone_rates

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| delivery_mode_id | `foreignId` | constrained('delivery_modes'), cascadeOnDelete |
| zone_id | `foreignId` | constrained('delivery_zones'), cascadeOnDelete |
| rate | `decimal(15, 2)` |  |
| additional_item_rate | `decimal(15, 2)` | default(0) |
| min_weight | `decimal(10, 2)` | default(0) |
| max_weight | `decimal(10, 2)` | nullable |
| currency_code | `string(3)` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['delivery_mode_id', 'zone_id'])`

### handling_unit_items

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete, name('hui_org_fk') |
| handling_unit_id | `foreignId` | constrained('handling_units'), cascadeOnDelete, name('hui_hu_fk') |
| product_id | `foreignId` | nullable, constrained('products'), nullOnDelete, name('hui_product_fk') |
| inventory_batch_id | `foreignId` | nullable, constrained('inventory_batches'), nullOnDelete, name('hui_batch_fk') |
| sales_order_line_id | `foreignId` | nullable, constrained('sales_order_lines'), nullOnDelete, name('hui_sol_fk') |
| quantity | `decimal(18, 4)` |  |
| weight | `decimal(10, 4)` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['handling_unit_id'], 'hui_hu_idx')`

### handling_units

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| shipment_id | `foreignId` | nullable, constrained('shipments'), nullOnDelete, name('hu_shipment_fk') |
| sales_order_id | `foreignId` | nullable, constrained('sales_orders'), nullOnDelete, name('hu_so_fk') |
| hu_type | `enum(['box', 'pallet', 'container', 'bag', 'drum', 'other'])` | default('box') |
| hu_number | `string(50)` |  |
| sscc_number | `string(30)` | nullable |
| gross_weight | `decimal(10, 4)` | nullable |
| net_weight | `decimal(10, 4)` | nullable |
| volume | `decimal(10, 4)` | nullable |
| length | `decimal(10, 2)` | nullable |
| width | `decimal(10, 2)` | nullable |
| height | `decimal(10, 2)` | nullable |
| is_sealed | `boolean` | default(false) |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'hu_number'], 'hu_org_number_unq')`
- `$table->index(['shipment_id'], 'hu_shipment_idx')`

### material_account_groups

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| group_code | `string(4)` | unique |
| description | `string` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade')`

### output_types

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| code | `string(10)` |  |
| name | `string(100)` |  |
| document_type | `enum(['invoice', 'sales_order', 'quotation', 'delivery_note', 'purchase_order', 'payment'])` | default('invoice') |
| output_medium | `enum(['print', 'email', 'edi', 'portal'])` | default('email') |
| email_template | `string(100)` | nullable |
| print_template | `string(100)` | nullable |
| dispatch_time | `enum(['immediately', 'on_save', 'on_post', 'scheduled'])` | default('on_post') |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'code', 'document_type'], 'ot_org_code_doc_unique')`

### output_condition_records

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| output_type_id | `foreignId` | constrained('output_types'), cascadeOnDelete |
| key_combination | `enum(['customer', 'customer_group', 'all'])` | default('all') |
| customer_id | `unsignedBigInteger` | nullable |
| customer_group_id | `unsignedBigInteger` | nullable |
| is_active | `boolean` | default(true) |
| valid_from | `date` | nullable |
| valid_to | `date` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['output_type_id', 'key_combination'], 'ocr_type_key_idx')`

### output_messages

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| output_type_id | `foreignId` | constrained('output_types'), cascadeOnDelete |
| document_type | `string(30)` |  |
| document_id | `unsignedBigInteger` |  |
| status | `enum(['pending', 'processing', 'sent', 'failed', 'cancelled'])` | default('pending') |
| medium | `enum(['print', 'email', 'edi', 'portal'])` | default('email') |
| recipient | `string(255)` | nullable |
| scheduled_at | `timestamp` | nullable |
| sent_at | `timestamp` | nullable |
| error_message | `text` | nullable |
| retry_count | `integer` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['document_type', 'document_id'], 'om_doc_idx')`
- `$table->index(['status', 'scheduled_at'], 'om_status_sched_idx')`

### payment_modes

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| name | `string` |  |
| code | `string(30)` |  |
| type | `string(30)` | cash, bank_transfer, card, cheque, upi, mobile_wallet, online, crypto, credit_term, cod |
| description | `text` | nullable |
| icon | `string` | nullable |
| bank_account_id | `foreignId` | nullable, constrained('bank_accounts'), nullOnDelete |
| account_id | `foreignId` | nullable, constrained('chart_of_accounts'), nullOnDelete |
| is_online | `boolean` | default(false) — Online payment method |
| requires_reference | `boolean` | default(false) — Transaction ref required |
| requires_approval | `boolean` | default(false) |
| surcharge_percent | `decimal(5, 2)` | default(0) — Card processing fee |
| surcharge_flat | `decimal(15, 2)` | default(0) |
| min_amount | `decimal(15, 2)` | nullable |
| max_amount | `decimal(15, 2)` | nullable |
| supported_currencies | `json` | nullable |
| gateway_provider | `string` | nullable — stripe, paypal, razorpay, tap |
| gateway_config | `json` | nullable |
| is_active | `boolean` | default(true) |
| display_order | `unsignedSmallInteger` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'code'])`
- `$table->index(['organization_id', 'is_active'])`
- `$table->index(['organization_id', 'type'])`

### price_lists

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| name | `string(100)` |  |
| code | `string(20)` |  |
| description | `text` | nullable |
| type | `string(20)` | default('selling') — selling, buying |
| currency_code | `string(3)` |  |
| is_default | `boolean` | default(false) |
| is_tax_inclusive | `boolean` | default(false) |
| valid_from | `date` | nullable |
| valid_until | `date` | nullable |
| customer_group_id | `foreignId` | nullable, constrained, nullOnDelete |
| priority | `unsignedSmallInteger` | default(0) |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'code'])`
- `$table->index(['organization_id', 'type', 'is_active'])`

### contacts

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| contact_type | `enum(['customer', 'supplier', 'both'])` | default('customer') |
| company_name | `string(200)` | nullable |
| contact_name | `string(100)` |  |
| email | `string(100)` | nullable |
| phone | `string(20)` | nullable |
| mobile | `string(20)` | nullable |
| website | `string(255)` | nullable |
| tax_number | `text` | nullable |
| tax_number_hash | `string(64)` | nullable |
| tax_registration_name | `string(200)` | nullable |
| payment_terms | `integer` | default(30) — Days |
| credit_limit | `decimal(18, 4)` | default(0) |
| currency_code | `string(3)` | default('SAR') |
| receivable_account_id | `foreignId` | nullable, constrained('chart_of_accounts'), nullOnDelete |
| payable_account_id | `foreignId` | nullable, constrained('chart_of_accounts'), nullOnDelete |
| billing_address_line_1 | `string(255)` | nullable |
| billing_address_line_2 | `string(255)` | nullable |
| billing_city | `string(100)` | nullable |
| billing_state | `string(100)` | nullable |
| billing_postal_code | `string(20)` | nullable |
| billing_country_code | `string(2)` | nullable |
| shipping_address_line_1 | `string(255)` | nullable |
| shipping_address_line_2 | `string(255)` | nullable |
| shipping_city | `string(100)` | nullable |
| shipping_state | `string(100)` | nullable |
| shipping_postal_code | `string(20)` | nullable |
| shipping_country_code | `string(2)` | nullable |
| notes | `text` | nullable |
| is_active | `boolean` | default(true) |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |
| customer_group_id | `foreignId` | nullable, constrained, nullOnDelete |
| default_price_list_id | `foreignId` | nullable, constrained('price_lists'), nullOnDelete |
| tax_exempt | `boolean` | default(false) |
| tax_exemption_number | `string(50)` | nullable |
| tax_exemption_expiry | `date` | nullable |
| import_export_code | `string(30)` | nullable — IEC for India, etc. |
| default_incoterm | `string(10)` | nullable |
| default_port | `string` | nullable |
| customer_account_group_id | `unsignedBigInteger` | nullable |
| payment_block | `boolean` | default(false) |
| payment_block_reason | `string(500)` | nullable |

Indexes:

- `$table->index(['organization_id', 'contact_type'])`
- `$table->index(['organization_id', 'company_name'])`
- `$table->index(['organization_id', 'tax_number_hash'])`

Foreign keys:

- `$table->foreign('customer_account_group_id', 'contact_cag_fk')->references('id')->on('customer_account_groups')->onDelete('set null')`

### consignment_orders

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| order_number | `string(30)` |  |
| order_type | `enum(['fillup', 'issue', 'pickup', 'return'])` |  |
| contact_id | `foreignId` | constrained('contacts') |
| status | `enum(['draft', 'confirmed', 'shipped', 'completed', 'cancelled'])` | default('draft') |
| order_date | `date` |  |
| notes | `text` | nullable |
| created_by | `foreignId` | constrained('users') |
| branch_id | `foreignId` | nullable, constrained('branches'), nullOnDelete |
| invoice_id | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'order_number'], 'co_org_order_number_unique')`
- `$table->index(['organization_id', 'order_type'], 'co_org_type_idx')`
- `$table->index(['organization_id', 'contact_id'], 'co_org_contact_idx')`
- `$table->index(['organization_id', 'status'], 'co_org_status_idx')`

### customer_credits

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| customer_id | `foreignId` | constrained('contacts') |
| source_type | `enum(['advance_payment', 'credit_note', 'overpayment', 'adjustment', ])` |  |
| source_id | `unsignedBigInteger` | nullable |
| original_amount | `decimal(18, 4)` |  |
| remaining_amount | `decimal(18, 4)` |  |
| currency_code | `string(3)` | default('SAR') |
| credit_date | `date` |  |
| notes | `text` | nullable |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'customer_id'])`
- `$table->index(['source_type', 'source_id'])`

### payments_received

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| branch_id | `foreignId` | nullable, constrained, nullOnDelete |
| payment_number | `string(50)` |  |
| payment_date | `date` |  |
| customer_id | `foreignId` | constrained('contacts') |
| bank_account_id | `foreignId` | nullable, constrained('bank_accounts'), nullOnDelete |
| payment_method | `enum(['cash', 'bank_transfer', 'cheque', 'credit_card', 'online', 'other', ])` | default('bank_transfer') |
| amount | `decimal(18, 4)` |  |
| currency_code | `string(3)` | default('SAR') |
| exchange_rate | `decimal(18, 8)` | default(1) |
| base_amount | `decimal(18, 4)` | In organization's base currency |
| reference | `string(100)` | nullable — Cheque number, transaction ID, etc. |
| notes | `text` | nullable |
| status | `enum(['pending', 'completed', 'voided', 'bounced', ])` | default('pending') |
| journal_entry_id | `foreignId` | nullable, constrained('journal_entries'), nullOnDelete |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'payment_number'])`
- `$table->index(['organization_id', 'customer_id'])`
- `$table->index(['organization_id', 'payment_date'])`
- `$table->index(['organization_id', 'status'])`

### price_list_assignments

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| price_list_id | `foreignId` | constrained('price_lists'), cascadeOnDelete |
| assignment_type | `enum(['contact', 'customer_group', 'all'])` |  |
| assignment_id | `unsignedBigInteger` | nullable |
| priority | `tinyInteger` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['price_list_id'], 'pla_list_idx')`
- `$table->index(['assignment_type', 'assignment_id'], 'pla_type_id_idx')`

### price_override_policies

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| name | `string` |  |
| description | `text` | nullable |
| allow_price_change | `boolean` | default(true) |
| allow_discount | `boolean` | default(true) |
| allow_markup | `boolean` | default(false) — Above list price |
| allow_free_item | `boolean` | default(false) — Price = 0 |
| max_discount_percent | `decimal(5, 2)` | nullable — Max % below list price |
| max_markup_percent | `decimal(5, 2)` | nullable — Max % above list price |
| max_discount_amount | `decimal(15, 2)` | nullable — Max flat discount per item |
| min_price_percent | `decimal(5, 2)` | nullable — Floor price as % of cost |
| max_total_discount_percent | `decimal(5, 2)` | nullable — Max % off entire order |
| requires_approval | `boolean` | default(false) |
| approval_threshold_percent | `decimal(5, 2)` | nullable — Needs approval above this % |
| approval_threshold_amount | `decimal(15, 2)` | nullable — Needs approval above this amount |
| requires_reason | `boolean` | default(true) |
| applies_to | `string(30)` | default('all') — all, roles, users, branches |
| applicable_role_ids | `json` | nullable |
| applicable_user_ids | `json` | nullable |
| applicable_branch_ids | `json` | nullable |
| is_default | `boolean` | default(false) |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'is_active'])`

### price_override_reasons

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| name | `string` |  |
| code | `string(30)` |  |
| description | `text` | nullable |
| requires_approval | `boolean` | default(false) |
| requires_evidence | `boolean` | default(false) — Attach competitor price screenshot, etc. |
| is_active | `boolean` | default(true) |
| display_order | `unsignedSmallInteger` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'code'])`

### pricing_condition_types

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| code | `string(10)` | PR00, K007, MWST, etc. |
| name | `string(100)` |  |
| condition_class | `enum(['price', 'discount', 'surcharge', 'tax', 'freight'])` | default('price') |
| calculation_type | `enum(['fixed', 'percentage', 'quantity', 'weight', 'volume'])` | default('percentage') |
| is_mandatory | `boolean` | default(false) |
| step | `integer` | default(10) |
| counter | `integer` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'code'], 'pct_org_code_unique')`

### pricing_condition_records

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| condition_type_id | `foreignId` | constrained('pricing_condition_types'), cascadeOnDelete |
| key_combination | `enum(['customer_material', 'customer', 'material', 'price_list', 'all'])` | default('material') |
| customer_id | `unsignedBigInteger` | nullable |
| product_id | `unsignedBigInteger` | nullable |
| price_list_id | `unsignedBigInteger` | nullable |
| rate | `decimal(15, 4)` |  |
| currency_code | `string(3)` | default('SAR') |
| valid_from | `date` | nullable |
| valid_to | `date` | nullable |
| min_quantity | `decimal(15, 4)` | nullable |
| max_quantity | `decimal(15, 4)` | nullable |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['condition_type_id', 'key_combination'], 'pcr_type_key_idx')`
- `$table->index(['product_id', 'valid_from', 'valid_to'], 'pcr_product_dates_idx')`

### pricing_procedures

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string(36)` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| code | `string(20)` |  |
| name | `string(100)` |  |
| is_default | `boolean` | default(false) |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'code'], 'pp_org_code_unique')`

### product_attributes

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| name | `string` |  |
| code | `string(30)` |  |
| type | `string(20)` | text, number, select, multi_select, boolean, color |
| options | `json` | nullable — For select/multi_select types |
| unit | `string` | nullable — kg, cm, ml |
| is_filterable | `boolean` | default(false) |
| is_comparable | `boolean` | default(false) — Show in product comparison |
| is_visible | `boolean` | default(true) |
| display_order | `unsignedSmallInteger` | default(0) |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'code'])`

### product_bundles

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| name | `string` |  |
| sku | `string(50)` |  |
| description | `text` | nullable |
| image_path | `string` | nullable |
| pricing_type | `string(20)` | default('fixed') — fixed, percentage_discount, custom |
| bundle_price | `decimal(15, 2)` | nullable — For fixed pricing |
| discount_percent | `decimal(5, 2)` | nullable — For percentage discount |
| original_total | `decimal(15, 2)` | default(0) — Sum of individual items |
| savings_amount | `decimal(15, 2)` | default(0) — How much customer saves |
| available_from | `date` | nullable |
| available_until | `date` | nullable |
| is_limited_time | `boolean` | default(false) |
| max_quantity | `unsignedInteger` | nullable — Max bundles available |
| sold_quantity | `unsignedInteger` | default(0) |
| min_order_quantity | `unsignedSmallInteger` | default(1) |
| max_order_quantity | `unsignedSmallInteger` | nullable |
| eligible_customer_tiers | `json` | nullable — Tier codes allowed |
| is_featured | `boolean` | default(false) |
| is_active | `boolean` | default(true) |
| display_order | `unsignedSmallInteger` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'sku'])`
- `$table->index(['organization_id', 'is_active'])`
- `$table->index(['available_from', 'available_until'])`

### product_tags

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| name | `string` |  |
| slug | `string(50)` |  |
| color | `string(7)` | nullable |
| tag_group | `string(30)` | nullable — season, material, brand, origin, diet, etc. |
| description | `text` | nullable |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'slug'])`
- `$table->index(['organization_id', 'tag_group'])`

### promotions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| name | `string(100)` |  |
| code | `string(30)` | nullable — Promo code for manual entry |
| description | `text` | nullable |
| type | `string(30)` |  |
| apply_to | `string(20)` | default('line') — line, order, shipping |
| target | `string(30)` | default('all') |
| discount_value | `decimal(15, 4)` | nullable |
| max_discount_amount | `decimal(15, 4)` | nullable — Cap for percentage discounts |
| buy_quantity | `unsignedInteger` | nullable |
| get_quantity | `unsignedInteger` | nullable |
| get_discount_percent | `decimal(5, 2)` | nullable — 100 = free |
| tiers | `json` | nullable |
| min_order_amount | `decimal(15, 4)` | nullable |
| min_quantity | `decimal(15, 4)` | nullable |
| max_uses | `unsignedInteger` | nullable |
| max_uses_per_customer | `unsignedInteger` | nullable |
| current_uses | `unsignedInteger` | default(0) |
| start_date | `datetime` |  |
| end_date | `datetime` | nullable |
| valid_days | `json` | nullable — [0,1,2,3,4,5,6] days of week |
| valid_time_start | `time` | nullable |
| valid_time_end | `time` | nullable |
| is_stackable | `boolean` | default(false) — Can combine with other promotions |
| is_exclusive | `boolean` | default(false) — Only one exclusive promo per order |
| priority | `unsignedSmallInteger` | default(0) |
| is_active | `boolean` | default(true) |
| requires_code | `boolean` | default(false) |
| created_by | `foreignId` | constrained('users'), cascadeOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'code'])`
- `$table->index(['organization_id', 'is_active', 'start_date', 'end_date'])`

### coupon_codes

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| promotion_id | `foreignId` | constrained, cascadeOnDelete |
| code | `string(30)` | unique |
| max_uses | `unsignedInteger` | nullable |
| current_uses | `unsignedInteger` | default(0) |
| times_used | `unsignedInteger` | default(0) — Alias for current_uses |
| assigned_to | `foreignId` | nullable, constrained('contacts'), nullOnDelete |
| assigned_to_contact_id | `foreignId` | nullable, constrained('contacts'), nullOnDelete |
| expires_at | `datetime` | nullable |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['code', 'is_active'])`

### promotion_usages

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| promotion_id | `foreignId` | constrained, cascadeOnDelete |
| contact_id | `foreignId` | nullable, constrained, cascadeOnDelete |
| order_type | `string(50)` | Invoice, SalesOrder |
| order_id | `unsignedBigInteger` |  |
| discount_amount | `decimal(15, 4)` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['promotion_id', 'contact_id'])`
- `$table->index(['order_type', 'order_id'])`

### quick_sale_templates

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| name | `string` |  |
| description | `text` | nullable |
| default_items | `json` | nullable — Pre-configured line items |
| default_customer_id | `foreignId` | nullable, constrained('contacts'), nullOnDelete |
| default_payment_method | `string` | nullable |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'is_active'])`

### quotations

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| branch_id | `foreignId` | nullable, constrained, nullOnDelete |
| quotation_number | `string(50)` |  |
| customer_id | `foreignId` | constrained('contacts') |
| customer_name | `string(200)` |  |
| customer_email | `string(100)` | nullable |
| billing_address | `text` | nullable |
| shipping_address | `text` | nullable |
| quotation_date | `date` |  |
| valid_until | `date` |  |
| currency_code | `string(3)` | default('SAR') |
| exchange_rate | `decimal(18, 8)` | default(1) |
| subtotal | `decimal(18, 4)` | default(0) |
| discount_type | `enum(['percentage', 'fixed'])` | nullable |
| discount_value | `decimal(18, 4)` | default(0) |
| discount_amount | `decimal(18, 4)` | default(0) |
| tax_amount | `decimal(18, 4)` | default(0) |
| total | `decimal(18, 4)` | default(0) |
| status | `enum(['draft', 'sent', 'accepted', 'declined', 'expired', 'converted', ])` | default('draft') |
| salesperson_id | `foreignId` | nullable, constrained('users'), nullOnDelete |
| notes | `text` | nullable |
| terms_and_conditions | `text` | nullable |
| reference | `string(100)` | nullable |
| version | `unsignedInteger` | default(1) |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'quotation_number'])`
- `$table->index(['organization_id', 'customer_id'])`
- `$table->index(['organization_id', 'status'])`

## 0270_sales_2.php

### rebate_masters

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| name | `string` |  |
| description | `text` | nullable |
| contact_id | `unsignedBigInteger` |  |
| rebate_type | `string` | percentage, fixed_amount, tiered |
| calculation_base | `string` | invoice_value, quantity, gross_profit |
| rebate_rate | `decimal(8, 4)` | default(0) |
| accrual_method | `string` | periodic, on_invoice |
| valid_from | `date` |  |
| valid_to | `date` | nullable |
| minimum_purchase | `decimal(15, 4)` | nullable |
| maximum_rebate | `decimal(15, 4)` | nullable |
| accrual_account_id | `unsignedBigInteger` | nullable |
| expense_account_id | `unsignedBigInteger` | nullable |
| status | `string` | default('active') — active, inactive |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'contact_id', 'status'])`
- `$table->index(['organization_id', 'valid_from', 'valid_to'])`

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade')`
- `$table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade')`
- `$table->foreign('accrual_account_id')->references('id')->on('chart_of_accounts')->onDelete('set null')`
- `$table->foreign('expense_account_id')->references('id')->on('chart_of_accounts')->onDelete('set null')`

### return_policies

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| name | `string` |  |
| description | `text` | nullable |
| return_window_days | `unsignedSmallInteger` | default(30) — Days after purchase |
| allow_exchange | `boolean` | default(true) |
| allow_refund | `boolean` | default(true) |
| allow_credit_note | `boolean` | default(true) |
| require_receipt | `boolean` | default(true) |
| require_original_packaging | `boolean` | default(false) |
| require_approval | `boolean` | default(true) |
| restocking_fee_percent | `decimal(5, 2)` | default(0) |
| non_returnable_categories | `json` | nullable — Category IDs that can't be returned |
| condition_requirements | `json` | nullable — Condition must be X to return |
| is_default | `boolean` | default(false) |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'is_active'])`

### return_reasons

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| name | `string` |  |
| code | `string(30)` |  |
| description | `text` | nullable |
| requires_evidence | `boolean` | default(false) |
| is_active | `boolean` | default(true) |
| display_order | `unsignedSmallInteger` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'code'])`

### revenue_account_determination_keys

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| customer_account_group | `string(4)` | nullable |
| material_account_group | `string(4)` | nullable |
| condition_type | `string(4)` | nullable |
| gl_account_id | `unsignedBigInteger` |  |
| valid_from | `date` |  |
| valid_to | `date` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade')`
- `$table->foreign('gl_account_id', 'rev_acct_det_gl_fk')->references('id')->on('chart_of_accounts')->onDelete('restrict')`

### revenue_contracts

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| contract_number | `string` |  |
| contact_id | `unsignedBigInteger` |  |
| contract_date | `date` |  |
| total_transaction_price | `decimal(15, 4)` | default(0) |
| allocated_price | `decimal(15, 4)` | default(0) |
| status | `string` | default('draft') — draft, active, completed, cancelled |
| recognition_method | `string` | point_in_time, over_time |
| start_date | `date` | nullable |
| end_date | `date` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'contract_number'])`
- `$table->index(['organization_id', 'status'])`
- `$table->index(['organization_id', 'contact_id'])`

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade')`
- `$table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade')`

### performance_obligations

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| revenue_contract_id | `unsignedBigInteger` |  |
| description | `string` |  |
| standalone_selling_price | `decimal(15, 4)` | default(0) |
| allocated_transaction_price | `decimal(15, 4)` | default(0) |
| recognition_method | `string` | point_in_time, over_time, milestone |
| status | `string` | default('pending') — pending, in_progress, completed |
| completion_percentage | `decimal(5, 2)` | default(0) |
| recognized_amount | `decimal(15, 4)` | default(0) |
| deferred_amount | `decimal(15, 4)` | default(0) |
| revenue_account_id | `unsignedBigInteger` | nullable |
| deferred_account_id | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['revenue_contract_id', 'status'])`

Foreign keys:

- `$table->foreign('revenue_contract_id')->references('id')->on('revenue_contracts')->onDelete('cascade')`
- `$table->foreign('revenue_account_id')->references('id')->on('chart_of_accounts')->onDelete('set null')`
- `$table->foreign('deferred_account_id')->references('id')->on('chart_of_accounts')->onDelete('set null')`

### revenue_recognition_events

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| performance_obligation_id | `unsignedBigInteger` |  |
| event_date | `date` |  |
| amount_recognized | `decimal(15, 4)` |  |
| journal_entry_id | `unsignedBigInteger` | nullable |
| notes | `string` | nullable |
| created_by | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['performance_obligation_id', 'event_date'], 'rev_rec_event_perf_oblig_date_idx')`

Foreign keys:

- `$table->foreign('performance_obligation_id')->references('id')->on('performance_obligations')->onDelete('cascade')`
- `$table->foreign('journal_entry_id')->references('id')->on('journal_entries')->onDelete('set null')`
- `$table->foreign('created_by')->references('id')->on('users')->onDelete('set null')`

### sales_order_cost_estimate_items

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), name('socei_org_fk') |
| sales_order_cost_estimate_id | `foreignId` | constrained('sales_order_cost_estimates'), name('socei_estimate_fk') |
| sales_order_line_id | `foreignId` | nullable, constrained('sales_order_lines'), name('socei_sol_fk') |
| product_id | `foreignId` | nullable, constrained('products'), name('socei_product_fk') |
| cost_element_id | `foreignId` | nullable, constrained('cost_elements'), name('socei_ce_fk') |
| cost_category | `enum(['material', 'labor', 'overhead', 'other'])` |  |
| quantity | `decimal(18, 4)` |  |
| cost_per_unit | `decimal(18, 4)` |  |
| total_cost | `decimal(18, 4)` |  |
| revenue | `decimal(18, 4)` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['sales_order_cost_estimate_id'], 'socei_estimate_idx')`

### sales_order_cost_estimates

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations') |
| sales_order_id | `foreignId` | nullable, constrained('sales_orders'), name('soce_so_fk') |
| quotation_id | `foreignId` | nullable, constrained('quotations'), name('soce_quot_fk') |
| costing_version_id | `foreignId` | nullable, constrained('costing_versions'), name('soce_cv_fk') |
| status | `enum(['draft', 'released', 'obsolete'])` | default('draft') |
| total_cost | `decimal(18, 4)` | default(0) |
| total_revenue | `decimal(18, 4)` | default(0) |
| gross_margin | `decimal(18, 4)` | default(0) |
| gross_margin_percent | `decimal(8, 4)` | default(0) |
| costed_by | `foreignId` | nullable, constrained('users'), name('soce_costed_by_fk') |
| costed_at | `dateTime` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'sales_order_id'], 'soce_org_so_idx')`
- `$table->index(['organization_id', 'quotation_id'], 'soce_org_quot_idx')`

### sales_orders

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| branch_id | `foreignId` | nullable, constrained, nullOnDelete |
| order_number | `string(50)` |  |
| quotation_id | `foreignId` | nullable, constrained('quotations'), nullOnDelete |
| customer_id | `foreignId` | constrained('contacts') |
| customer_name | `string(200)` |  |
| customer_email | `string(100)` | nullable |
| billing_address | `text` | nullable |
| shipping_address | `text` | nullable |
| order_date | `date` |  |
| expected_delivery_date | `date` | nullable |
| delivery_date | `date` | nullable |
| currency_code | `string(3)` | default('SAR') |
| exchange_rate | `decimal(18, 8)` | default(1) |
| subtotal | `decimal(18, 4)` | default(0) |
| discount_type | `enum(['percentage', 'fixed'])` | nullable |
| discount_value | `decimal(18, 4)` | default(0) |
| discount_amount | `decimal(18, 4)` | default(0) |
| tax_amount | `decimal(18, 4)` | default(0) |
| total | `decimal(18, 4)` | default(0) |
| status | `enum(['draft', 'confirmed', 'processing', 'partially_delivered', 'delivered', 'invoiced', 'cancelled', ])` | default('draft') |
| salesperson_id | `foreignId` | nullable, constrained('users'), nullOnDelete |
| warehouse_id | `foreignId` | nullable, constrained, nullOnDelete — Default fulfillment warehouse |
| notes | `text` | nullable |
| delivery_instructions | `text` | nullable |
| reference | `string(100)` | nullable — Customer PO number |
| version | `unsignedInteger` | default(1) |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'order_number'])`
- `$table->index(['organization_id', 'customer_id'])`
- `$table->index(['organization_id', 'status'])`

### delivery_documents

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| delivery_number | `string` | unique |
| sales_order_id | `unsignedBigInteger` | nullable |
| ship_to_contact_id | `unsignedBigInteger` | nullable |
| warehouse_id | `unsignedBigInteger` | nullable |
| planned_goods_issue_date | `date` |  |
| actual_goods_issue_date | `date` | nullable |
| delivery_date | `date` | nullable |
| carrier | `string` | nullable |
| tracking_number | `string` | nullable |
| status | `enum(['created', 'picking', 'picked', 'packed', 'goods_issued', 'cancelled'])` | default('created') |
| weight_gross | `decimal(10, 3)` | nullable |
| weight_net | `decimal(10, 3)` | nullable |
| volume | `decimal(10, 3)` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade')`
- `$table->foreign('sales_order_id', 'del_doc_so_fk')->references('id')->on('sales_orders')->onDelete('set null')`
- `$table->foreign('ship_to_contact_id', 'del_doc_ship_fk')->references('id')->on('contacts')->onDelete('set null')`
- `$table->foreign('warehouse_id', 'del_doc_wh_fk')->references('id')->on('warehouses')->onDelete('set null')`

### intercompany_sales_orders

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| selling_organization_id | `unsignedBigInteger` |  |
| buying_organization_id | `unsignedBigInteger` |  |
| sales_order_id | `unsignedBigInteger` | nullable |
| order_number | `string(50)` |  |
| status | `enum(['draft', 'confirmed', 'in_delivery', 'billed', 'cancelled'])` | default('draft') |
| order_date | `date` |  |
| requested_delivery_date | `date` | nullable |
| transfer_price_version_id | `unsignedBigInteger` | nullable |
| currency_code | `char(3)` | default('SAR') |
| subtotal | `decimal(18, 4)` | default(0) |
| tax_amount | `decimal(18, 4)` | default(0) |
| total_amount | `decimal(18, 4)` | default(0) |
| notes | `text` | nullable |
| created_by | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['selling_organization_id', 'status'], 'icso_selling_org_status_idx')`
- `$table->index(['buying_organization_id'], 'icso_buying_org_idx')`
- `$table->unique(['selling_organization_id', 'order_number'], 'icso_org_order_number_unq')`

Foreign keys:

- `$table->foreign('selling_organization_id', 'icso_selling_org_fk')->references('id')->on('organizations')->restrictOnDelete()`
- `$table->foreign('buying_organization_id', 'icso_buying_org_fk')->references('id')->on('organizations')->restrictOnDelete()`
- `$table->foreign('sales_order_id', 'icso_sales_order_fk')->references('id')->on('sales_orders')->nullOnDelete()`
- `$table->foreign('transfer_price_version_id', 'icso_tpv_fk')->references('id')->on('transfer_price_versions')->nullOnDelete()`
- `$table->foreign('created_by', 'icso_created_by_fk')->references('id')->on('users')->nullOnDelete()`

### intercompany_billing_documents

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| intercompany_sales_order_id | `unsignedBigInteger` |  |
| selling_organization_id | `unsignedBigInteger` |  |
| buying_organization_id | `unsignedBigInteger` |  |
| document_number | `string(50)` |  |
| billing_date | `date` |  |
| currency_code | `char(3)` | default('SAR') |
| subtotal | `decimal(18, 4)` |  |
| tax_amount | `decimal(18, 4)` | default(0) |
| total_amount | `decimal(18, 4)` |  |
| status | `enum(['draft', 'posted', 'cancelled'])` | default('draft') |
| journal_entry_id | `unsignedBigInteger` | nullable |
| posted_at | `dateTime` | nullable |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['selling_organization_id', 'document_number'], 'icbd_org_doc_unq')`

Foreign keys:

- `$table->foreign('intercompany_sales_order_id', 'icbd_icso_fk')->references('id')->on('intercompany_sales_orders')->restrictOnDelete()`
- `$table->foreign('selling_organization_id', 'icbd_selling_org_fk')->references('id')->on('organizations')->restrictOnDelete()`
- `$table->foreign('buying_organization_id', 'icbd_buying_org_fk')->references('id')->on('organizations')->restrictOnDelete()`
- `$table->foreign('journal_entry_id', 'icbd_je_fk')->references('id')->on('journal_entries')->nullOnDelete()`

### invoices

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| branch_id | `foreignId` | nullable, constrained, nullOnDelete |
| invoice_number | `string(50)` |  |
| invoice_type | `enum(['standard', 'simplified', 'credit_note', 'debit_note', ])` | default('standard') |
| quotation_id | `unsignedBigInteger` | nullable |
| sales_order_id | `unsignedBigInteger` | nullable |
| original_invoice_id | `foreignId` | nullable, constrained('invoices'), nullOnDelete — For credit/debit notes |
| customer_id | `foreignId` | constrained('contacts') |
| customer_name | `string(200)` |  |
| customer_email | `string(100)` | nullable |
| customer_tax_number | `string(50)` | nullable |
| billing_address | `text` | nullable |
| shipping_address | `text` | nullable |
| invoice_date | `date` |  |
| due_date | `date` |  |
| currency_code | `string(3)` | default('SAR') |
| exchange_rate | `decimal(18, 8)` | default(1) |
| subtotal | `decimal(18, 4)` | default(0) |
| discount_type | `enum(['percentage', 'fixed'])` | nullable |
| discount_value | `decimal(18, 4)` | default(0) |
| discount_amount | `decimal(18, 4)` | default(0) |
| tax_amount | `decimal(18, 4)` | default(0) |
| total | `decimal(18, 4)` | default(0) |
| base_total | `decimal(18, 4)` | default(0) — In base currency |
| amount_paid | `decimal(18, 4)` | default(0) |
| amount_due | `decimal(18, 4)` | default(0) |
| status | `enum(['draft', 'sent', 'partial', 'paid', 'overdue', 'voided', ])` | default('draft') |
| compliance_status | `enum(['not_applicable', 'pending', 'submitted', 'cleared', 'reported', 'rejected', ])` | default('not_applicable') |
| compliance_uuid | `string(100)` | nullable |
| compliance_hash | `string(64)` | nullable |
| compliance_qr_code | `text` | nullable |
| compliance_response | `json` | nullable |
| compliance_submitted_at | `timestamp` | nullable |
| place_of_supply | `string(2)` | nullable — State code |
| is_reverse_charge | `boolean` | default(false) |
| salesperson_id | `foreignId` | nullable, constrained('users'), nullOnDelete |
| journal_entry_id | `foreignId` | nullable, constrained('journal_entries'), nullOnDelete |
| notes | `text` | nullable |
| terms_and_conditions | `text` | nullable |
| reference | `string(100)` | nullable — Customer PO number |
| version | `unsignedInteger` | default(1) |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |
| print_count | `integer` | default(0) |
| last_printed_at | `timestamp` | nullable |
| incoterm | `string(10)` | nullable |
| port_of_loading | `string` | nullable |
| port_of_discharge | `string` | nullable |
| country_of_destination | `string(3)` | nullable |
| is_international | `boolean` | default(false) |
| is_export | `boolean` | default(false) |
| compliance_notes | `text` | nullable |

Indexes:

- `$table->unique(['organization_id', 'invoice_number'])`
- `$table->index(['organization_id', 'customer_id'])`
- `$table->index(['organization_id', 'status'])`
- `$table->index(['organization_id', 'invoice_date'])`
- `$table->index(['organization_id', 'due_date', 'status'])`
- `$table->unique('compliance_uuid')`
- `$table->index('quotation_id')`
- `$table->index('sales_order_id')`
- `$table->index('compliance_status', 'inv_compliance_status_idx')`

Foreign keys:

- `$table->foreign('quotation_id')->references('id')->on('quotations')->nullOnDelete()`
- `$table->foreign('sales_order_id')->references('id')->on('sales_orders')->nullOnDelete()`

### cash_sales

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| cash_sale_number | `string` | unique |
| customer_id | `unsignedBigInteger` | nullable |
| cashier_id | `unsignedBigInteger` |  |
| branch_id | `unsignedBigInteger` | nullable |
| sale_date | `timestamp` |  |
| subtotal | `decimal(18, 4)` |  |
| tax_amount | `decimal(18, 4)` | default(0) |
| discount_amount | `decimal(18, 4)` | default(0) |
| total_amount | `decimal(18, 4)` |  |
| currency | `char(3)` | default('SAR') |
| payment_method | `enum(['cash', 'card', 'wallet', 'mixed'])` |  |
| amount_tendered | `decimal(18, 4)` | nullable |
| change_given | `decimal(18, 4)` | nullable |
| invoice_id | `unsignedBigInteger` | nullable |
| status | `enum(['open', 'completed', 'voided'])` | default('open') |
| void_reason | `text` | nullable |
| voided_by | `unsignedBigInteger` | nullable |
| voided_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade')`
- `$table->foreign('customer_id', 'cs_cust_fk')->references('id')->on('contacts')->onDelete('set null')`
- `$table->foreign('cashier_id', 'cs_cashier_fk')->references('id')->on('users')->onDelete('restrict')`
- `$table->foreign('branch_id', 'cs_branch_fk')->references('id')->on('branches')->onDelete('set null')`
- `$table->foreign('invoice_id', 'cs_inv_fk')->references('id')->on('invoices')->onDelete('set null')`
- `$table->foreign('voided_by', 'cs_void_usr_fk')->references('id')->on('users')->onDelete('set null')`

### commission_calculations

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| commission_master_id | `unsignedBigInteger` |  |
| invoice_id | `unsignedBigInteger` | nullable |
| sales_order_id | `unsignedBigInteger` | nullable |
| period_year | `unsignedSmallInteger` |  |
| period_month | `tinyInteger` |  |
| base_amount | `decimal(18, 4)` |  |
| commission_rate | `decimal(8, 4)` |  |
| commission_amount | `decimal(18, 4)` |  |
| currency | `char(3)` | default('SAR') |
| status | `enum(['calculated', 'approved', 'paid', 'reversed'])` | default('calculated') |
| calculated_at | `timestamp` |  |
| approved_by | `unsignedBigInteger` | nullable |
| paid_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade')`
- `$table->foreign('commission_master_id', 'comm_calc_master_fk')->references('id')->on('commission_masters')->onDelete('cascade')`
- `$table->foreign('invoice_id', 'comm_calc_inv_fk')->references('id')->on('invoices')->onDelete('set null')`
- `$table->foreign('sales_order_id', 'comm_calc_so_fk')->references('id')->on('sales_orders')->onDelete('set null')`
- `$table->foreign('approved_by', 'comm_calc_appr_fk')->references('id')->on('users')->onDelete('set null')`

### payment_allocations

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| payment_received_id | `foreignId` | constrained('payments_received'), cascadeOnDelete |
| invoice_id | `foreignId` | constrained, cascadeOnDelete |
| amount | `decimal(18, 4)` |  |
| base_amount | `decimal(18, 4)` |  |
| allocated_at | `timestamp` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['payment_received_id', 'invoice_id'])`
- `$table->index('invoice_id')`

### pick_documents

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| pick_number | `string` | unique |
| delivery_document_id | `unsignedBigInteger` |  |
| assigned_to | `unsignedBigInteger` | nullable |
| status | `enum(['open', 'in_progress', 'completed', 'cancelled'])` | default('open') |
| started_at | `timestamp` | nullable |
| completed_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade')`
- `$table->foreign('delivery_document_id', 'pick_doc_del_fk')->references('id')->on('delivery_documents')->onDelete('cascade')`
- `$table->foreign('assigned_to', 'pick_doc_usr_fk')->references('id')->on('users')->onDelete('set null')`

### rebate_accruals

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| rebate_master_id | `unsignedBigInteger` |  |
| invoice_id | `unsignedBigInteger` |  |
| accrual_date | `date` |  |
| invoice_amount | `decimal(15, 4)` |  |
| rebate_amount | `decimal(15, 4)` |  |
| journal_entry_id | `unsignedBigInteger` | nullable |
| status | `string` | default('pending') — pending, posted, settled |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| settlement_ref | `string(100)` | nullable |
| settled_at | `timestamp` | nullable |

Indexes:

- `$table->index(['rebate_master_id', 'status'])`
- `$table->index(['invoice_id'])`

Foreign keys:

- `$table->foreign('rebate_master_id')->references('id')->on('rebate_masters')->onDelete('cascade')`
- `$table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade')`
- `$table->foreign('journal_entry_id')->references('id')->on('journal_entries')->onDelete('set null')`

### seasonal_campaigns

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| name | `string` |  |
| code | `string(30)` |  |
| description | `text` | nullable |
| campaign_type | `string(30)` | seasonal, flash_sale, clearance, holiday, back_to_school, ramadan, diwali, eid, national_day |
| banner_image | `string` | nullable |
| theme_color | `string(7)` | nullable |
| starts_at | `timestamp` |  |
| ends_at | `timestamp` |  |
| is_recurring | `boolean` | default(false) — Same campaign every year |
| recurrence_rule | `string` | nullable — yearly, quarterly |
| discount_type | `string(20)` | nullable — percentage, fixed_amount, tiered |
| discount_value | `decimal(15, 2)` | nullable |
| max_discount | `decimal(15, 2)` | nullable |
| min_purchase | `decimal(15, 2)` | nullable |
| applies_to | `string(30)` | default('all') — all, categories, products, bundles |
| applicable_category_ids | `json` | nullable |
| applicable_product_ids | `json` | nullable |
| applicable_bundle_ids | `json` | nullable |
| excluded_product_ids | `json` | nullable |
| max_uses | `unsignedInteger` | nullable |
| max_uses_per_customer | `unsignedInteger` | nullable |
| times_used | `unsignedInteger` | default(0) |
| budget_limit | `decimal(15, 2)` | nullable — Max total discount given |
| budget_used | `decimal(15, 2)` | default(0) |
| promotional_message | `text` | nullable |
| send_notification | `boolean` | default(false) |
| show_countdown | `boolean` | default(false) |
| is_active | `boolean` | default(true) |
| priority | `unsignedSmallInteger` | default(0) — Higher = checked first |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'code'])`
- `$table->index(['organization_id', 'is_active', 'starts_at', 'ends_at'], 'season_camp_org_active_dates_idx')`
- `$table->index(['campaign_type'])`

### campaign_tier_offers

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| campaign_id | `foreignId` | constrained('seasonal_campaigns'), cascadeOnDelete |
| tier_code | `string(30)` | nullable — Links to customer_tiers.code |
| tier_name | `string(100)` | nullable |
| min_purchase_amount | `decimal(15, 2)` | nullable |
| discount_type | `string(20)` | nullable — percentage, fixed_amount |
| discount_value | `decimal(15, 2)` | nullable |
| max_discount | `decimal(15, 2)` | nullable |
| extra_discount_percent | `decimal(5, 2)` | default(0) |
| bonus_points | `unsignedInteger` | default(0) |
| early_access | `boolean` | default(false) |
| early_access_hours | `unsignedSmallInteger` | default(0) |
| description | `text` | nullable |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['campaign_id', 'tier_code'])`

### shipments

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| delivery_mode_id | `foreignId` | constrained('delivery_modes'), cascadeOnDelete |
| shipment_number | `string(30)` |  |
| source_type | `string(100)` | SalesOrder, Invoice, ExchangeOrder |
| source_id | `unsignedBigInteger` | nullable |
| contact_id | `foreignId` | constrained('contacts'), cascadeOnDelete |
| shipping_address | `json` |  |
| billing_address | `json` | nullable |
| tracking_number | `string` | nullable |
| carrier | `string` | nullable |
| tracking_url | `string` | nullable |
| ship_date | `date` | nullable |
| estimated_delivery | `date` | nullable |
| actual_delivery | `date` | nullable |
| total_weight_kg | `decimal(10, 2)` | nullable |
| dimensions | `json` | nullable — {length, width, height} |
| shipping_cost | `decimal(15, 2)` | default(0) |
| currency_code | `string(3)` |  |
| status | `string(30)` | default('pending') — pending, picked, packed, shipped, in_transit, out_for_delivery, delivered, failed, returned |
| notes | `text` | nullable |
| delivery_notes | `text` | nullable |
| proof_of_delivery_path | `string` | nullable |
| created_by | `foreignId` | constrained('users'), cascadeOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'shipment_number'])`
- `$table->index(['organization_id', 'status'])`
- `$table->index(['source_type', 'source_id'])`
- `$table->index(['contact_id'])`
- `$table->index(['tracking_number'])`

### shipment_tracking_events

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| shipment_id | `foreignId` | constrained('shipments'), cascadeOnDelete |
| status | `string(50)` |  |
| description | `text` | nullable |
| location | `string` | nullable |
| event_at | `timestamp` |  |
| raw_data | `json` | nullable — Carrier API response |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['shipment_id', 'event_at'])`

### shipping_route_determinations

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete, name('srd_org_fk') |
| sales_order_id | `foreignId` | nullable, constrained('sales_orders'), nullOnDelete, name('srd_so_fk') |
| shipment_id | `foreignId` | nullable, constrained('shipments'), nullOnDelete, name('srd_shipment_fk') |
| shipping_route_id | `foreignId` | nullable, constrained('shipping_routes'), nullOnDelete, name('srd_route_fk') |
| departure_zone_id | `foreignId` | nullable, constrained('shipping_zones'), nullOnDelete, name('srd_dep_zone_fk') |
| destination_zone_id | `foreignId` | nullable, constrained('shipping_zones'), nullOnDelete, name('srd_dest_zone_fk') |
| determined_at | `dateTime` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['sales_order_id'], 'srd_so_idx')`

### shipping_routes

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete, name('sr_org_fk') |
| route_code | `string(30)` |  |
| route_name | `string(100)` |  |
| departure_zone_id | `foreignId` | constrained('shipping_zones'), cascadeOnDelete, name('sr_dep_zone_fk') |
| destination_zone_id | `foreignId` | constrained('shipping_zones'), cascadeOnDelete, name('sr_dest_zone_fk') |
| transportation_mode | `enum(['road', 'air', 'sea', 'rail', 'courier'])` | default('road') |
| transit_days | `unsignedSmallInteger` | default(1) |
| carrier | `string(100)` | nullable |
| freight_cost | `decimal(18, 4)` | default(0) |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'route_code'], 'sr_org_code_unq')`
- `$table->index(['departure_zone_id', 'destination_zone_id'], 'sr_dep_dest_idx')`

### shipping_zones

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| zone_code | `string(20)` |  |
| zone_name | `string(100)` |  |
| country_codes | `json` | nullable |
| postal_code_pattern | `string(100)` | nullable |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'zone_code'], 'sz_org_code_unq')`

### third_party_order_lines

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete, name('tpol_org_fk') |
| third_party_order_id | `foreignId` | constrained('third_party_orders'), cascadeOnDelete, name('tpol_tpo_fk') |
| sales_order_line_id | `foreignId` | nullable, constrained('sales_order_lines'), nullOnDelete, name('tpol_sol_fk') |
| product_id | `foreignId` | constrained('products'), cascadeOnDelete, name('tpol_product_fk') |
| quantity | `decimal(18, 4)` |  |
| unit_price | `decimal(18, 4)` |  |
| vendor_price | `decimal(18, 4)` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['third_party_order_id'], 'tpol_tpo_idx')`

### third_party_orders

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| sales_order_id | `foreignId` | constrained('sales_orders'), cascadeOnDelete, name('tpo_so_fk') |
| vendor_id | `foreignId` | constrained('contacts'), cascadeOnDelete, name('tpo_vendor_fk') |
| purchase_order_id | `foreignId` | nullable, constrained('purchase_orders'), nullOnDelete, name('tpo_po_fk') |
| status | `enum(['pending', 'po_created', 'shipped', 'delivered', 'invoiced', 'cancelled'])` | default('pending') |
| shipping_address_line1 | `string(255)` | nullable |
| shipping_address_line2 | `string(255)` | nullable |
| shipping_city | `string(100)` | nullable |
| shipping_country_code | `char(2)` | nullable |
| vendor_reference | `string(100)` | nullable |
| shipping_confirmation | `string(100)` | nullable |
| estimated_delivery_date | `date` | nullable |
| actual_delivery_date | `date` | nullable |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'status'], 'tpo_org_status_idx')`
- `$table->index(['sales_order_id'], 'tpo_so_idx')`

### wallets

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| contact_id | `foreignId` | constrained('contacts'), cascadeOnDelete |
| wallet_type | `string(20)` | customer, supplier |
| currency_code | `string(3)` | default('SAR') |
| balance | `decimal(15, 2)` | default(0) — Current balance |
| credit_limit | `decimal(15, 2)` | default(0) — For credit wallets |
| total_credits | `decimal(15, 2)` | default(0) — Lifetime credits added |
| total_debits | `decimal(15, 2)` | default(0) — Lifetime debits |
| is_active | `boolean` | default(true) |
| allow_negative | `boolean` | default(false) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'contact_id', 'currency_code'])`
- `$table->index(['organization_id', 'wallet_type'])`

### wallet_transactions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| wallet_id | `foreignId` | constrained, cascadeOnDelete |
| transaction_type | `string(30)` | credit, debit, adjustment, refund, transfer |
| reference_number | `string(50)` | nullable |
| amount | `decimal(15, 2)` |  |
| balance_before | `decimal(15, 2)` |  |
| balance_after | `decimal(15, 2)` |  |
| description | `string` |  |
| source_type | `string` | nullable |
| source_id | `unsignedBigInteger` | nullable |
| transaction_date | `date` | nullable |
| metadata | `json` | nullable |
| created_by | `foreignId` | nullable, constrained('users'), cascadeOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `nullableMorphs('source')`
- `$table->index(['wallet_id', 'transaction_date'])`

### advance_payments

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| branch_id | `foreignId` | nullable, constrained, nullOnDelete |
| payment_number | `string(30)` |  |
| payment_type | `string(20)` | customer_advance, supplier_advance |
| contact_id | `foreignId` | constrained('contacts'), cascadeOnDelete |
| contact_name | `string` |  |
| payment_date | `date` |  |
| currency_code | `string(3)` | default('SAR') |
| exchange_rate | `decimal(10, 6)` | default(1) |
| amount | `decimal(15, 2)` |  |
| base_amount | `decimal(15, 2)` |  |
| applied_amount | `decimal(15, 2)` | default(0) — Amount used against invoices |
| refunded_amount | `decimal(15, 2)` | default(0) |
| available_amount | `decimal(15, 2)` | amount - applied - refunded |
| payment_method | `string(30)` |  |
| bank_account_id | `foreignId` | nullable, constrained('bank_accounts'), nullOnDelete |
| reference | `string` | nullable |
| cheque_number | `string` | nullable |
| cheque_date | `date` | nullable |
| status | `string(20)` | default('active') — active, fully_applied, refunded, cancelled |
| journal_entry_id | `foreignId` | nullable, constrained('journal_entries'), nullOnDelete |
| wallet_id | `foreignId` | nullable, constrained, nullOnDelete |
| wallet_transaction_id | `foreignId` | nullable |
| notes | `text` | nullable |
| received_by | `foreignId` | constrained('users'), cascadeOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'payment_type', 'status'])`
- `$table->index(['contact_id', 'status'])`

Foreign keys:

- `$table->foreign('wallet_transaction_id', 'adv_pay_wallet_txn_fk')->references('id')->on('wallet_transactions')->nullOnDelete()`

### advance_payment_applications

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| advance_payment_id | `foreignId` | constrained, cascadeOnDelete |
| applied_to_type | `string` |  |
| applied_to_id | `unsignedBigInteger` |  |
| applied_amount | `decimal(15, 2)` |  |
| applied_date | `date` |  |
| applied_by | `foreignId` | constrained('users'), cascadeOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `morphs('applied_to')`

## 0280_accounting_6.php

### credit_exposures

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string(36)` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| contact_id | `foreignId` | constrained('contacts'), cascadeOnDelete |
| snapshot_date | `date` |  |
| open_invoices | `decimal(15, 4)` | default(0) |
| open_orders | `decimal(15, 4)` | default(0) |
| total_exposure | `decimal(15, 4)` | default(0) |
| credit_limit | `decimal(15, 4)` | default(0) |
| available_credit | `decimal(15, 4)` | default(0) |
| utilization_pct | `decimal(5, 2)` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'contact_id', 'snapshot_date'], 'credit_exp_org_contact_date_uniq')`
- `$table->index(['organization_id', 'snapshot_date'], 'credit_exposures_org_date_idx')`
- `$table->index(['contact_id', 'snapshot_date'], 'credit_exposures_contact_date_idx')`

### credit_holds

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string(36)` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| contact_id | `foreignId` | constrained('contacts'), cascadeOnDelete |
| held_at | `timestamp` |  |
| released_at | `timestamp` | nullable |
| hold_reason | `string(500)` |  |
| release_reason | `string(500)` | nullable |
| held_by | `foreignId` | constrained('users') |
| released_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'contact_id'], 'credit_holds_org_contact_idx')`
- `$table->index(['organization_id', 'released_at'], 'credit_holds_org_released_idx')`

### credit_limits

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string(36)` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| contact_id | `foreignId` | constrained('contacts'), cascadeOnDelete |
| credit_limit | `decimal(15, 4)` |  |
| currency_code | `string(3)` | default('SAR') |
| valid_from | `date` |  |
| valid_until | `date` | nullable |
| payment_terms_days | `smallInteger` | unsigned, default(30) |
| risk_class | `enum(['low', 'medium', 'high', 'blocked'])` | default('medium') |
| last_reviewed_at | `timestamp` | nullable |
| reviewed_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'contact_id'], 'credit_limits_org_contact_unique')`
- `$table->index(['organization_id', 'risk_class'], 'credit_limits_org_risk_idx')`

### currency_revaluation_items

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| revaluation_id | `foreignId` | constrained('currency_revaluations'), cascadeOnDelete |
| account_id | `foreignId` | constrained('chart_of_accounts'), cascadeOnDelete |
| account_type | `string(30)` | receivable, payable, bank, asset, liability |
| foreign_currency_balance | `decimal(18, 4)` | Balance in foreign currency |
| old_base_amount | `decimal(18, 4)` | Balance at old rate |
| new_base_amount | `decimal(18, 4)` | Balance at new rate |
| gain_loss_amount | `decimal(18, 4)` | Difference |
| contact_id | `foreignId` | nullable, constrained('contacts'), nullOnDelete — For AR/AP |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['revaluation_id'])`
- `$table->index(['account_id'])`

### dunning_blocks

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string(36)` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| contact_id | `foreignId` | constrained('contacts'), cascadeOnDelete |
| blocked_until | `date` | nullable |
| reason | `string(500)` |  |
| blocked_by | `foreignId` | constrained('users') |
| released_at | `timestamp` | nullable |
| released_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| release_reason | `string(500)` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'contact_id'], 'dunning_blocks_org_contact_idx')`
- `$table->index(['organization_id', 'released_at'], 'dunning_blocks_org_released_idx')`

### dunning_notices

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string(36)` | unique |
| dunning_run_id | `foreignId` | constrained('dunning_runs'), cascadeOnDelete |
| contact_id | `foreignId` | constrained('contacts'), cascadeOnDelete |
| dunning_level_id | `foreignId` | constrained('dunning_levels') |
| total_overdue | `decimal(15, 4)` |  |
| currency_code | `string(3)` | default('SAR') |
| notice_date | `date` |  |
| sent_at | `timestamp` | nullable |
| status | `enum(['pending', 'sent', 'failed', 'blocked'])` | default('pending') |
| blocking_reason | `string(200)` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['dunning_run_id', 'status'], 'dunning_notices_run_status_idx')`
- `$table->index(['contact_id', 'status'], 'dunning_notices_contact_status_idx')`

### dunning_notice_items

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| dunning_notice_id | `foreignId` | constrained('dunning_notices'), cascadeOnDelete |
| invoice_id | `foreignId` | constrained('invoices'), cascadeOnDelete |
| invoice_number | `string(50)` |  |
| invoice_date | `date` |  |
| due_date | `date` |  |
| original_amount | `decimal(15, 4)` |  |
| outstanding_amount | `decimal(15, 4)` |  |
| days_overdue | `smallInteger` | unsigned |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['dunning_notice_id'], 'dunning_notice_items_notice_idx')`
- `$table->index(['invoice_id'], 'dunning_notice_items_invoice_idx')`

### journal_entry_lines

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| journal_entry_id | `foreignId` | constrained, cascadeOnDelete |
| account_id | `foreignId` | constrained('chart_of_accounts'), cascadeOnDelete |
| description | `text` | nullable |
| debit | `decimal(18, 4)` | default(0) |
| credit | `decimal(18, 4)` | default(0) |
| base_debit | `decimal(18, 4)` | default(0) |
| base_credit | `decimal(18, 4)` | default(0) |
| cost_center_id | `foreignId` | nullable |
| contact_id | `unsignedBigInteger` | nullable — Customer/Supplier |
| line_order | `unsignedSmallInteger` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| ledger_id | `unsignedBigInteger` | nullable, comment('NULL = leading ledger; non-null = parallel ledger (IFRS/tax/mgmt)') |
| segment_id | `string(50)` | nullable |
| category | `string(50)` | nullable |
| profit_center_id | `foreignId` | nullable, constrained('profit_centers'), nullOnDelete |

Indexes:

- `$table->index(['journal_entry_id', 'line_order'])`
- `$table->index(['account_id'])`
- `$table->index(['ledger_id'])`

Foreign keys:

- `$table->foreign('contact_id', 'jel_contact_fk')->references('id')->on('contacts')->nullOnDelete()`
- `$table->foreign('cost_center_id', 'jel_cost_center_fk')->references('id')->on('cost_centers')->nullOnDelete()`

### loans

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| branch_id | `foreignId` | nullable, constrained, nullOnDelete |
| loan_number | `string(30)` |  |
| loan_type | `string(30)` | employee_loan, inter_company, intra_company, bank_loan |
| loan_category | `string(50)` | nullable — personal, salary_advance, housing, vehicle, education |
| employee_id | `foreignId` | nullable, constrained, nullOnDelete |
| contact_id | `foreignId` | nullable, constrained('contacts'), nullOnDelete — For inter-company |
| borrower_name | `string` | nullable |
| lender_type | `string(30)` | organization, bank, other |
| lender_name | `string` | nullable |
| lender_contact_id | `foreignId` | nullable, constrained('contacts'), nullOnDelete |
| principal_amount | `decimal(15, 2)` |  |
| interest_rate | `decimal(5, 2)` | default(0) — Annual rate |
| interest_type | `string(20)` | default('simple') — simple, compound, flat |
| total_interest | `decimal(15, 2)` | default(0) |
| total_amount | `decimal(15, 2)` |  |
| outstanding_amount | `decimal(15, 2)` |  |
| currency_code | `string(3)` | default('SAR') |
| disbursement_date | `date` |  |
| first_payment_date | `date` |  |
| maturity_date | `date` |  |
| tenure_months | `unsignedInteger` |  |
| payment_frequency | `string(20)` | default('monthly') — weekly, bi-weekly, monthly |
| emi_amount | `decimal(15, 2)` | Equated Monthly Installment |
| total_installments | `unsignedInteger` |  |
| paid_installments | `unsignedInteger` | default(0) |
| status | `string(20)` | default('pending') — pending, approved, active, completed, defaulted, written_off |
| approval_status | `string(20)` | default('pending') — pending, approved, rejected |
| loan_account_id | `foreignId` | nullable, constrained('chart_of_accounts'), nullOnDelete |
| interest_account_id | `foreignId` | nullable, constrained('chart_of_accounts'), nullOnDelete |
| bank_account_id | `foreignId` | nullable, constrained('bank_accounts'), nullOnDelete |
| deduct_from_payroll | `boolean` | default(false) |
| monthly_deduction | `decimal(15, 2)` | nullable |
| purpose | `text` | nullable |
| terms_conditions | `text` | nullable |
| documents | `json` | nullable |
| approved_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| approved_at | `timestamp` | nullable |
| created_by | `foreignId` | constrained('users'), cascadeOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'status'])`
- `$table->index(['employee_id', 'status'])`
- `$table->index(['loan_type', 'status'])`

### inter_company_transfers

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| transfer_number | `string(30)` |  |
| transfer_type | `string(30)` | fund_transfer, loan, investment |
| from_branch_id | `foreignId` | nullable, constrained('branches'), nullOnDelete |
| from_bank_account_id | `foreignId` | nullable, constrained('bank_accounts'), nullOnDelete |
| to_branch_id | `foreignId` | nullable, constrained('branches'), nullOnDelete |
| to_bank_account_id | `foreignId` | nullable, constrained('bank_accounts'), nullOnDelete |
| to_organization_id | `foreignId` | nullable — For inter-company |
| amount | `decimal(15, 2)` |  |
| currency_code | `string(3)` | default('SAR') |
| transfer_date | `date` |  |
| reference | `string` | nullable |
| purpose | `text` | nullable |
| status | `string(20)` | default('pending') — pending, approved, completed, cancelled |
| journal_entry_id | `foreignId` | nullable, constrained('journal_entries'), nullOnDelete |
| loan_id | `foreignId` | nullable, constrained, nullOnDelete |
| created_by | `foreignId` | constrained('users'), cascadeOnDelete |
| approved_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| approved_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'transfer_date'])`
- `$table->index(['from_branch_id', 'to_branch_id'])`

### loan_schedules

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| loan_id | `foreignId` | constrained, cascadeOnDelete |
| installment_number | `unsignedInteger` |  |
| due_date | `date` |  |
| principal_amount | `decimal(15, 2)` |  |
| interest_amount | `decimal(15, 2)` |  |
| total_amount | `decimal(15, 2)` |  |
| outstanding_balance | `decimal(15, 2)` |  |
| status | `string(20)` | default('pending') — pending, paid, partial, overdue |
| paid_amount | `decimal(15, 2)` | default(0) |
| paid_date | `date` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['loan_id', 'installment_number'])`
- `$table->index(['loan_id', 'due_date', 'status'])`

### loan_payments

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| loan_id | `foreignId` | constrained, cascadeOnDelete |
| schedule_id | `foreignId` | nullable, constrained('loan_schedules'), nullOnDelete |
| payment_date | `date` |  |
| principal_paid | `decimal(15, 2)` | default(0) |
| interest_paid | `decimal(15, 2)` | default(0) |
| penalty_paid | `decimal(15, 2)` | default(0) |
| total_paid | `decimal(15, 2)` |  |
| payment_method | `string(30)` | cash, bank_transfer, payroll_deduction |
| reference | `string` | nullable |
| journal_entry_id | `foreignId` | nullable, constrained('journal_entries'), nullOnDelete |
| payroll_id | `foreignId` | nullable — If deducted from payroll |
| notes | `text` | nullable |
| received_by | `foreignId` | constrained('users'), cascadeOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['loan_id', 'payment_date'])`

Foreign keys:

- `$table->foreign('payroll_id', 'loan_payment_payroll_period_fk')->references('id')->on('payroll_periods')->nullOnDelete()`

### aml_cdd_records

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| contact_id | `foreignId` | constrained('contacts'), cascadeOnDelete |
| cdd_level | `string` | standard, enhanced, simplified |
| status | `string` | pending, completed, expired, failed |
| verification_data | `json` | nullable |
| verified_at | `date` | nullable |
| expires_at | `date` | nullable |
| verified_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'contact_id'])`

### aml_risk_scores

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| contact_id | `foreignId` | constrained('contacts'), cascadeOnDelete |
| score | `unsignedTinyInteger` | 0-100 composite risk score |
| risk_level | `string` | low (0-30), medium (31-60), high (61-80), critical (81-100) |
| score_breakdown | `json` | per-dimension contributions |
| sanctions_hit | `boolean` | default(false) |
| pep_hit | `boolean` | default(false) |
| sanctions_details | `string` | nullable |
| last_screened_at | `timestamp` | nullable |
| score_updated_at | `timestamp` | useCurrent |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'contact_id'])`
- `$table->index(['organization_id', 'risk_level'])`

### aml_screening_cache

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| contact_id | `foreignId` | constrained('contacts'), cascadeOnDelete |
| list_type | `string` | ofac, eu, un, pep, local |
| is_match | `boolean` | default(false) |
| match_details | `json` | nullable |
| data_hash | `string` | hash of contact fields screened — skip if unchanged |
| screened_at | `timestamp` |  |
| created_at | `timestamp` | useCurrent |

Indexes:

- `$table->unique(['contact_id', 'list_type'])`
- `$table->index(['organization_id', 'is_match'])`

### aml_suspicious_activities

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| report_type | `string` | SAR, CTR (currency transaction report), STR |
| status | `string` | default('draft') — draft, filed, closed |
| contact_id | `foreignId` | nullable, constrained('contacts'), nullOnDelete |
| contact_name | `string` | nullable — denormalized for reports |
| related_transaction_ids | `json` | nullable |
| description | `text` |  |
| activity_type | `string` | structuring, smurfing, layering, unusual_pattern, sanctions_hit |
| total_amount | `decimal(20, 4)` | nullable |
| currency | `string(3)` | nullable |
| activity_date_from | `date` | nullable |
| activity_date_to | `date` | nullable |
| narrative | `text` | nullable — full narrative for filing |
| created_by | `foreignId` | nullable, constrained('users'), cascadeOnDelete |
| filed_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| filed_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'status'])`

### aml_transaction_flags

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| transaction_type | `string` | invoice, payment, journal_entry |
| transaction_id | `unsignedBigInteger` |  |
| transaction_number | `string` | nullable |
| amount | `decimal(20, 4)` |  |
| currency | `string(3)` |  |
| flag_reason | `string` | large_cash, structuring, rapid_movement, threshold_breach, unusual_pattern |
| status | `string` | default('flagged') — flagged, cleared, escalated |
| aml_score | `unsignedInteger` | default(0) |
| context | `json` | supporting data |
| contact_id | `foreignId` | nullable, constrained('contacts'), nullOnDelete |
| transaction_date | `timestamp` |  |
| created_at | `timestamp` | useCurrent |

Indexes:

- `$table->index(['organization_id', 'status'])`
- `$table->index(['transaction_type', 'transaction_id'])`

### dim_customer

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `unsignedBigInteger` |  |
| contact_id | `unsignedBigInteger` | nullable |
| customer_code | `string(50)` |  |
| customer_name | `string` |  |
| customer_group | `string(50)` | nullable |
| country_code | `char(3)` | nullable |
| city | `string(100)` | nullable |
| currency_code | `char(3)` |  |
| credit_limit | `decimal(18, 4)` | nullable |
| is_active | `boolean` | default(true) |
| synced_at | `dateTime` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('organization_id', 'dim_customer_org_id_idx')`

Foreign keys:

- `$table->foreign('contact_id', 'dim_customer_contact_id_fk')->references('id')->on('contacts')->onDelete('set null')`

### dim_vendor

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `unsignedBigInteger` |  |
| contact_id | `unsignedBigInteger` | nullable |
| vendor_code | `string(50)` |  |
| vendor_name | `string` |  |
| vendor_group | `string(50)` | nullable |
| country_code | `char(3)` | nullable |
| currency_code | `char(3)` |  |
| payment_terms | `string(30)` | nullable |
| is_active | `boolean` | default(true) |
| synced_at | `dateTime` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('organization_id', 'dim_vendor_org_id_idx')`

Foreign keys:

- `$table->foreign('contact_id', 'dim_vendor_contact_id_fk')->references('id')->on('contacts')->onDelete('set null')`

### gdpr_consent_records

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| contact_id | `unsignedBigInteger` | nullable |
| user_id | `unsignedBigInteger` | nullable |
| purpose | `string` |  |
| consent_given | `boolean` | default(false) |
| given_at | `timestamp` | nullable |
| withdrawn_at | `timestamp` | nullable |
| ip_address | `string` | nullable |
| consent_text | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete()`
- `$table->foreign('contact_id', 'gdpr_con_contact_fk')->references('id')->on('contacts')->nullOnDelete()`
- `$table->foreign('user_id', 'gdpr_con_usr_fk')->references('id')->on('users')->nullOnDelete()`

### portal_users

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| contact_id | `foreignId` | constrained('contacts'), cascadeOnDelete |
| email | `string(150)` |  |
| password_hash | `string(255)` |  |
| is_active | `boolean` | default(true) |
| email_verified_at | `dateTime` | nullable |
| last_login_at | `dateTime` | nullable |
| login_count | `integer` | default(0) |
| password_reset_token | `string(100)` | nullable |
| password_reset_expires_at | `dateTime` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'email'], 'portal_users_org_email_unique')`
- `$table->index(['contact_id', 'is_active'], 'portal_users_contact_active_idx')`

### portal_activity_logs

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| portal_user_id | `foreignId` | constrained('portal_users'), cascadeOnDelete |
| activity_type | `string(50)` |  |
| description | `string(200)` |  |
| metadata | `json` | nullable |
| created_at | `dateTime` |  |

### portal_document_accesses

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| portal_user_id | `foreignId` | constrained('portal_users'), cascadeOnDelete |
| document_type | `string(30)` | invoice\|quotation\|order\|credit_note\|statement |
| document_id | `unsignedBigInteger` |  |
| accessed_at | `dateTime` |  |
| ip_address | `string(45)` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['portal_user_id', 'document_type'], 'portal_doc_acc_user_type_idx')`

### portal_sessions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| portal_user_id | `foreignId` | constrained('portal_users'), cascadeOnDelete |
| session_token | `string(255)` |  |
| ip_address | `string(45)` | nullable |
| user_agent | `text` | nullable |
| expires_at | `dateTime` |  |
| last_activity_at | `dateTime` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('session_token', 'portal_sessions_token_idx')`
- `$table->index('portal_user_id', 'portal_sessions_user_idx')`

### leads

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| lead_number | `string(50)` | nullable |
| title | `string(200)` | nullable |
| lead_type | `enum(['individual', 'company'])` | default('company') |
| company_name | `string(200)` | nullable |
| industry | `string(100)` | nullable |
| website | `string(200)` | nullable |
| employee_count | `unsignedInteger` | nullable |
| annual_revenue | `decimal(15, 2)` | nullable |
| contact_name | `string(200)` |  |
| contact_title | `string(100)` | nullable |
| email | `string(200)` | nullable |
| phone | `string(30)` | nullable |
| mobile | `string(30)` | nullable |
| address_line_1 | `string(200)` | nullable |
| address_line_2 | `string(200)` | nullable |
| city | `string(100)` | nullable |
| state | `string(100)` | nullable |
| postal_code | `string(20)` | nullable |
| country_code | `string(2)` | nullable |
| lead_source_id | `foreignId` | nullable, constrained, nullOnDelete |
| source_details | `string(200)` | nullable — Campaign name, referrer, etc. |
| assigned_to | `foreignId` | nullable, constrained('users'), nullOnDelete |
| branch_id | `foreignId` | nullable, constrained, nullOnDelete |
| status | `enum(['new', 'contacted', 'qualified', 'unqualified', 'converted', 'lost', ])` | default('new') |
| lost_reason | `string(500)` | nullable |
| lead_score | `unsignedSmallInteger` | default(0) — 0-100 |
| rating | `enum(['hot', 'warm', 'cold'])` | default('cold') |
| estimated_value | `decimal(15, 4)` | nullable |
| currency_code | `string(3)` | default('SAR') |
| converted_contact_id | `foreignId` | nullable, constrained('contacts'), nullOnDelete |
| converted_opportunity_id | `foreignId` | nullable |
| converted_at | `datetime` | nullable |
| converted_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| description | `text` | nullable |
| notes | `text` | nullable |
| tags | `json` | nullable |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'lead_number'])`
- `$table->index(['organization_id', 'status'])`
- `$table->index(['organization_id', 'assigned_to'])`
- `$table->index(['organization_id', 'lead_source_id'])`

Added by later migrations:

- `0470_deferred_keys.php`: `$table->foreign('converted_opportunity_id', 'lead_converted_opp_fk')->references('id')->on('opportunities')->nullOnDelete()`

### opportunities

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| opportunity_number | `string(50)` | nullable |
| name | `string(200)` |  |
| description | `text` | nullable |
| contact_id | `foreignId` | nullable, constrained, nullOnDelete |
| lead_id | `foreignId` | nullable, constrained, nullOnDelete |
| account_name | `string(200)` | nullable — Company name |
| pipeline_stage_id | `foreignId` | nullable, constrained, nullOnDelete |
| probability | `unsignedSmallInteger` | default(0) — 0-100 |
| amount | `decimal(15, 4)` | nullable |
| currency_code | `string(3)` | default('SAR') |
| expected_revenue | `decimal(15, 4)` | nullable — amount * probability |
| expected_close_date | `date` | nullable |
| actual_close_date | `date` | nullable |
| status | `enum(['open', 'won', 'lost', 'suspended', ])` | default('open') |
| lost_reason | `string(500)` | nullable |
| won_reason | `string(500)` | nullable |
| assigned_to | `foreignId` | nullable, constrained('users'), nullOnDelete |
| branch_id | `foreignId` | nullable, constrained, nullOnDelete |
| lead_source_id | `foreignId` | nullable, constrained, nullOnDelete |
| quotation_id | `foreignId` | nullable |
| sales_order_id | `foreignId` | nullable |
| notes | `text` | nullable |
| tags | `json` | nullable |
| competitors | `json` | nullable |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'opportunity_number'])`
- `$table->index(['organization_id', 'status'])`
- `$table->index(['organization_id', 'pipeline_stage_id'])`
- `$table->index(['organization_id', 'assigned_to'])`
- `$table->index(['organization_id', 'expected_close_date'])`

Foreign keys:

- `$table->foreign('quotation_id', 'opp_quotation_fk')->references('id')->on('quotations')->nullOnDelete()`
- `$table->foreign('sales_order_id', 'opp_sales_order_fk')->references('id')->on('sales_orders')->nullOnDelete()`

### service_tickets

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| branch_id | `unsignedBigInteger` | nullable |
| ticket_number | `string` | unique |
| subject | `string` |  |
| description | `text` |  |
| status | `string` | default('open') — open, in_progress, pending_customer, resolved, closed, cancelled |
| priority | `string` | default('medium') — low, medium, high, critical |
| type | `string` | default('general') — bug, feature_request, billing, technical, general |
| source | `string` | default('manual') — email, phone, portal, chat, manual |
| contact_id | `unsignedBigInteger` | nullable |
| assigned_to | `unsignedBigInteger` | nullable |
| team_id | `unsignedBigInteger` | nullable |
| sla_policy_id | `unsignedBigInteger` | nullable |
| first_response_due_at | `dateTime` | nullable |
| resolution_due_at | `dateTime` | nullable |
| first_response_at | `dateTime` | nullable |
| resolved_at | `dateTime` | nullable |
| closed_at | `dateTime` | nullable |
| sla_breached | `boolean` | default(false) |
| resolution_notes | `text` | nullable |
| customer_rating | `tinyInteger` | nullable |
| customer_feedback | `text` | nullable |
| created_by | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'status'])`
- `$table->index(['organization_id', 'priority'])`
- `$table->index(['organization_id', 'assigned_to'])`
- `$table->index(['organization_id', 'contact_id'])`
- `$table->index('sla_breached')`
- `$table->index('resolution_due_at')`

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade')`
- `$table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null')`
- `$table->foreign('contact_id')->references('id')->on('contacts')->onDelete('set null')`
- `$table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null')`
- `$table->foreign('sla_policy_id')->references('id')->on('sla_policies')->onDelete('set null')`
- `$table->foreign('created_by')->references('id')->on('users')->onDelete('set null')`

### service_ticket_comments

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| ticket_id | `unsignedBigInteger` |  |
| user_id | `unsignedBigInteger` |  |
| body | `text` |  |
| is_internal | `boolean` | default(false) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['ticket_id', 'is_internal'])`

Foreign keys:

- `$table->foreign('ticket_id')->references('id')->on('service_tickets')->onDelete('cascade')`
- `$table->foreign('user_id')->references('id')->on('users')->onDelete('cascade')`

### ecommerce_channels

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| name | `string` |  |
| platform | `string(30)` | shopify, woocommerce, magento, custom, marketplace |
| platform_name | `string` | nullable — Noon, Amazon, etc. |
| store_url | `string` | nullable |
| credentials | `json` | nullable — Encrypted API keys |
| settings | `json` | nullable |
| default_warehouse_id | `foreignId` | nullable, constrained('warehouses'), nullOnDelete |
| default_customer_id | `foreignId` | nullable, constrained('contacts'), nullOnDelete |
| sync_products | `boolean` | default(true) |
| sync_orders | `boolean` | default(true) |
| sync_inventory | `boolean` | default(true) |
| auto_fulfill | `boolean` | default(false) |
| last_sync_at | `timestamp` | nullable |
| status | `string(20)` | default('active') — active, paused, disconnected |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'status'])`

### ecommerce_orders

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| channel_id | `foreignId` | constrained('ecommerce_channels'), cascadeOnDelete |
| external_order_id | `string` |  |
| order_number | `string` |  |
| status | `string(30)` | pending, processing, shipped, delivered, cancelled, refunded |
| financial_status | `string(30)` | nullable — pending, paid, partially_paid, refunded |
| fulfillment_status | `string(30)` | nullable — unfulfilled, partial, fulfilled |
| customer_email | `string` | nullable |
| customer_name | `string` | nullable |
| customer_phone | `string` | nullable |
| customer_id | `foreignId` | nullable, constrained('contacts'), nullOnDelete |
| shipping_address | `json` | nullable |
| billing_address | `json` | nullable |
| currency_code | `string(3)` |  |
| subtotal | `decimal(15, 2)` |  |
| discount_amount | `decimal(15, 2)` | default(0) |
| shipping_amount | `decimal(15, 2)` | default(0) |
| tax_amount | `decimal(15, 2)` | default(0) |
| total_amount | `decimal(15, 2)` |  |
| shipping_method | `string` | nullable |
| tracking_number | `string` | nullable |
| tracking_url | `string` | nullable |
| sales_order_id | `foreignId` | nullable — Linked ERP sales order |
| invoice_id | `foreignId` | nullable, constrained('invoices'), nullOnDelete |
| is_processed | `boolean` | default(false) |
| processed_at | `timestamp` | nullable |
| raw_data | `json` | nullable — Original order data |
| notes | `text` | nullable |
| ordered_at | `timestamp` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['channel_id', 'external_order_id'])`
- `$table->index(['organization_id', 'status'])`
- `$table->index(['channel_id', 'ordered_at'])`

Foreign keys:

- `$table->foreign('sales_order_id', 'ecomm_order_sales_order_fk')->references('id')->on('sales_orders')->nullOnDelete()`

### ecommerce_sync_logs

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| channel_id | `foreignId` | constrained('ecommerce_channels'), cascadeOnDelete |
| sync_type | `string(30)` | products, orders, inventory, customers |
| direction | `string(10)` | push, pull |
| status | `string(20)` | started, completed, failed |
| total_records | `unsignedInteger` | default(0) |
| processed_records | `unsignedInteger` | default(0) |
| failed_records | `unsignedInteger` | default(0) |
| errors | `json` | nullable |
| started_at | `timestamp` |  |
| completed_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['channel_id', 'created_at'])`

### invoice_qr_codes

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| invoice_id | `foreignId` | constrained, cascadeOnDelete |
| qr_type | `string(30)` | zatca, payment_link, custom |
| qr_data | `text` | Encoded data |
| qr_image_path | `string` | nullable |
| payment_link | `string` | nullable |
| payment_amount | `decimal(15, 2)` | nullable |
| expires_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['invoice_id', 'qr_type'])`

### online_payments

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| gateway_id | `foreignId` | constrained('payment_gateways'), cascadeOnDelete |
| payable_type | `string` |  |
| payable_id | `unsignedBigInteger` |  |
| external_payment_id | `string` | nullable — Gateway transaction ID |
| status | `string(20)` | pending, authorized, captured, failed, refunded |
| currency_code | `string(3)` |  |
| amount | `decimal(15, 2)` |  |
| fee_amount | `decimal(15, 2)` | default(0) |
| net_amount | `decimal(15, 2)` |  |
| payment_method | `string(30)` | nullable — card, mada, apple_pay |
| card_brand | `string` | nullable |
| card_last4 | `string(4)` | nullable |
| gateway_response | `json` | nullable |
| failure_reason | `text` | nullable |
| ip_address | `string(45)` | nullable |
| payment_received_id | `foreignId` | nullable — Linked to payments_received |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `morphs('payable')`
- `$table->index(['organization_id', 'status'])`
- `$table->index('external_payment_id')`

Foreign keys:

- `$table->foreign('payment_received_id', 'online_pay_payment_received_fk')->references('id')->on('payments_received')->nullOnDelete()`

### recurring_expenses

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| category_id | `foreignId` | constrained('expense_categories'), cascadeOnDelete |
| supplier_id | `foreignId` | nullable, constrained('contacts'), nullOnDelete |
| name | `string` |  |
| description | `text` | nullable |
| amount | `decimal(15, 2)` |  |
| currency_code | `string(3)` | default('SAR') |
| frequency | `string(20)` | daily, weekly, monthly, quarterly, yearly |
| frequency_interval | `unsignedTinyInteger` | default(1) |
| start_date | `date` |  |
| end_date | `date` | nullable |
| next_occurrence | `date` |  |
| occurrences_count | `unsignedInteger` | default(0) |
| max_occurrences | `unsignedInteger` | nullable |
| auto_approve | `boolean` | default(false) |
| is_active | `boolean` | default(true) |
| created_by | `foreignId` | constrained('users'), cascadeOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'next_occurrence'])`
- `$table->index(['organization_id', 'is_active'])`

### fraud_alerts

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| fraud_rule_id | `foreignId` | constrained, cascadeOnDelete |
| entity_type | `string` | invoice, payment, login, contact |
| entity_id | `unsignedBigInteger` |  |
| entity_uuid | `string` | nullable |
| user_id | `foreignId` | nullable, constrained, nullOnDelete |
| contact_id | `foreignId` | nullable |
| severity | `string` |  |
| status | `string` | default('open') — open, reviewing, resolved, false_positive |
| fraud_score | `unsignedInteger` | default(0) |
| evidence | `json` | context data captured at alert time |
| ip_address | `string(45)` | nullable |
| reviewer_notes | `text` | nullable |
| reviewed_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| reviewed_at | `timestamp` | nullable |
| created_at | `timestamp` | useCurrent |

Indexes:

- `$table->index(['organization_id', 'status', 'severity'])`
- `$table->index(['entity_type', 'entity_id'])`

Foreign keys:

- `$table->foreign('contact_id', 'fraud_alert_contact_fk')->references('id')->on('contacts')->nullOnDelete()`

### price_check_stations

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| branch_id | `foreignId` | constrained, cascadeOnDelete |
| name | `string` |  |
| station_code | `string(30)` |  |
| location_description | `string` | nullable — "Aisle 3", "Near entrance" |
| device_type | `string(30)` | default('kiosk') — kiosk, handheld, mobile, tablet, pos |
| device_id | `string` | nullable — Hardware identifier |
| scanner_type | `string(30)` | default('laser') — laser, camera, rfid, nfc |
| scan_barcode | `boolean` | default(true) |
| scan_qr | `boolean` | default(true) |
| scan_rfid | `boolean` | default(false) |
| scan_nfc | `boolean` | default(false) |
| manual_entry | `boolean` | default(true) — Type SKU/barcode manually |
| show_price | `boolean` | default(true) |
| show_stock | `boolean` | default(false) |
| show_promotions | `boolean` | default(true) |
| show_alternatives | `boolean` | default(false) |
| show_loyalty_points | `boolean` | default(false) |
| show_product_image | `boolean` | default(true) |
| show_description | `boolean` | default(true) |
| show_location | `boolean` | default(false) — Aisle/shelf location |
| price_list_id | `foreignId` | nullable, constrained('price_lists'), nullOnDelete |
| use_customer_price | `boolean` | default(false) — Scan loyalty card first |
| api_token | `string(64)` | unique |
| status | `string(20)` | default('active') — active, inactive, maintenance |
| last_heartbeat_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'station_code'])`
- `$table->index(['branch_id', 'status'])`
- `$table->index(['api_token'])`

### truck_appointments

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| warehouse_id | `foreignId` | constrained('warehouses'), cascadeOnDelete |
| vendor_id | `foreignId` | nullable, constrained('contacts'), nullOnDelete |
| appointment_number | `string(20)` |  |
| scheduled_arrival | `dateTime` |  |
| scheduled_departure | `dateTime` | nullable |
| actual_arrival | `dateTime` | nullable |
| actual_departure | `dateTime` | nullable |
| dock_door_id | `foreignId` | nullable, constrained('dock_doors'), nullOnDelete |
| yard_zone_id | `foreignId` | nullable, constrained('yard_zones'), nullOnDelete |
| vehicle_plate | `string(20)` | nullable |
| driver_name | `string(100)` | nullable |
| driver_phone | `string(30)` | nullable |
| appointment_type | `string(20)` | default('delivery'), comment('delivery/pickup/both') |
| reference_type | `string(30)` | nullable, comment('purchase_order/sales_order/transfer') |
| reference_id | `unsignedBigInteger` | nullable |
| status | `string(20)` | default('scheduled'), comment('scheduled/checked_in/docked/loading/departed/cancelled') |
| notes | `text` | nullable |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['warehouse_id', 'status'], 'truck_appt_wh_status_idx')`
- `$table->index(['scheduled_arrival', 'status'], 'truck_appt_sched_status_idx')`
- `$table->index(['dock_door_id', 'status'], 'truck_appt_dock_status_idx')`
- `$table->index(['vendor_id', 'scheduled_arrival'], 'truck_appt_vendor_sched_idx')`
- `$table->unique(['warehouse_id', 'appointment_number'], 'truck_appt_wh_num_uq')`

### yard_movements

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| truck_appointment_id | `foreignId` | constrained('truck_appointments'), cascadeOnDelete |
| from_zone_id | `foreignId` | nullable, constrained('yard_zones'), nullOnDelete |
| to_zone_id | `foreignId` | nullable, constrained('yard_zones'), nullOnDelete |
| from_dock_id | `foreignId` | nullable, constrained('dock_doors'), nullOnDelete |
| to_dock_id | `foreignId` | nullable, constrained('dock_doors'), nullOnDelete |
| movement_type | `string(20)` | comment('arrival/move_to_dock/move_to_zone/departure') |
| moved_at | `dateTime` |  |
| moved_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('truck_appointment_id', 'yard_mov_appt_idx')`

## 0290_loyalty.php

### maintenance_order_cost_lines

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| maintenance_order_id | `unsignedBigInteger` |  |
| cost_element_id | `foreignId` | nullable, constrained('cost_elements'), nullOnDelete |
| cost_type | `string(30)` | comment('labor/material/external/overhead') |
| quantity | `decimal(18, 4)` | nullable |
| unit_cost | `decimal(18, 4)` | nullable |
| total_cost | `decimal(18, 4)` |  |
| currency_code | `string(3)` |  |
| posting_date | `date` |  |
| vendor_id | `foreignId` | nullable, constrained('contacts'), nullOnDelete |
| employee_id | `foreignId` | nullable, constrained('employees'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['maintenance_order_id', 'cost_type'], 'mo_cost_line_order_type_idx')`

### complaints

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| complaint_number | `string(50)` | unique |
| complaint_source | `enum(['customer', 'internal', 'regulatory', 'supplier'])` | default('customer') |
| contact_id | `unsignedBigInteger` | nullable |
| subject | `string` |  |
| description | `text` |  |
| priority | `enum(['critical', 'high', 'medium', 'low'])` | default('medium') |
| status | `enum(['open', 'investigating', 'resolved', 'closed', 'withdrawn'])` | default('open') |
| assigned_to_id | `unsignedBigInteger` | nullable |
| received_date | `date` |  |
| target_resolution_date | `date` | nullable |
| actual_resolution_date | `date` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete()`
- `$table->foreign('contact_id', 'complaint_contact_fk')->references('id')->on('contacts')->nullOnDelete()`
- `$table->foreign('assigned_to_id', 'complaint_assignee_fk')->references('id')->on('users')->nullOnDelete()`

### complaint_communications

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| complaint_id | `unsignedBigInteger` |  |
| direction | `enum(['inbound', 'outbound'])` | default('outbound') |
| channel | `enum(['email', 'phone', 'letter', 'portal', 'in_person'])` | default('email') |
| content | `text` |  |
| user_id | `unsignedBigInteger` |  |
| communicated_at | `timestamp` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('complaint_id', 'complaint_comm_fk')->references('id')->on('complaints')->cascadeOnDelete()`
- `$table->foreign('user_id', 'complaint_comm_user_fk')->references('id')->on('users')`

### complaint_resolutions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| complaint_id | `unsignedBigInteger` |  |
| resolution_type | `enum(['replacement', 'refund', 'credit', 'repair', 'explanation', 'apology', 'other'])` | default('other') |
| resolution_description | `text` |  |
| customer_accepted | `boolean` | default(false) |
| resolution_date | `date` |  |
| resolved_by_id | `unsignedBigInteger` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('complaint_id', 'complaint_res_fk')->references('id')->on('complaints')->cascadeOnDelete()`
- `$table->foreign('resolved_by_id', 'complaint_res_user_fk')->references('id')->on('users')`

### supplier_quality_ratings

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| supplier_id | `unsignedBigInteger` |  |
| rating_period_start | `date` |  |
| rating_period_end | `date` |  |
| quality_score | `decimal(5, 2)` | nullable |
| delivery_score | `decimal(5, 2)` | nullable |
| price_score | `decimal(5, 2)` | nullable |
| overall_score | `decimal(5, 2)` | nullable |
| classification | `enum(['preferred', 'approved', 'conditional', 'disqualified'])` | default('approved') |
| notes | `text` | nullable |
| evaluated_by_id | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete()`
- `$table->foreign('supplier_id', 'sq_rating_supplier_fk')->references('id')->on('contacts')->cascadeOnDelete()`
- `$table->foreign('evaluated_by_id', 'sq_rating_user_fk')->references('id')->on('users')->nullOnDelete()`

### contact_messaging_preferences

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| contact_id | `foreignId` | constrained('contacts'), cascadeOnDelete |
| email_enabled | `boolean` | default(true) |
| sms_enabled | `boolean` | default(true) |
| whatsapp_enabled | `boolean` | default(true) |
| push_enabled | `boolean` | default(true) |
| marketing_enabled | `boolean` | default(true) |
| transactional_enabled | `boolean` | default(true) |
| reminder_enabled | `boolean` | default(true) |
| preferred_channel | `string(30)` | default('email') |
| preferred_language | `string(5)` | default('en') |
| timezone | `string` | nullable |
| quiet_hours | `json` | nullable — {"start": "22:00", "end": "08:00"} |
| unsubscribed_at | `timestamp` | nullable |
| unsubscribe_reason | `string` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'contact_id'])`

### outbound_messages

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| automation_id | `foreignId` | nullable, constrained('messaging_automations'), nullOnDelete |
| template_id | `foreignId` | nullable, constrained('message_templates'), nullOnDelete |
| channel_id | `foreignId` | nullable, constrained('messaging_channels'), nullOnDelete |
| channel_type | `string(30)` |  |
| sender | `string(255)` | nullable |
| recipient | `string(255)` |  |
| recipient_name | `string` | nullable |
| contact_id | `foreignId` | nullable, constrained('contacts'), nullOnDelete |
| subject | `string` | nullable |
| body | `text` |  |
| html_body | `text` | nullable |
| entity_type | `string(100)` | nullable — invoice, payment, order |
| entity_id | `unsignedBigInteger` | nullable |
| category | `string(50)` | transactional, promotional, reminder |
| status | `string(20)` | default('queued') — queued, sending, sent, delivered, failed, bounced, opened, clicked |
| sent_at | `timestamp` | nullable |
| delivered_at | `timestamp` | nullable |
| opened_at | `timestamp` | nullable |
| clicked_at | `timestamp` | nullable |
| bounced_at | `timestamp` | nullable |
| failure_reason | `text` | nullable |
| provider_message_id | `string` | nullable |
| provider_response | `json` | nullable |
| cost | `decimal(10, 4)` | nullable — SMS/WhatsApp message cost |
| cost_currency | `string(3)` | nullable |
| retry_count | `unsignedTinyInteger` | default(0) |
| next_retry_at | `timestamp` | nullable |
| triggered_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'channel_type', 'created_at'])`
- `$table->index(['organization_id', 'status'])`
- `$table->index(['contact_id', 'created_at'])`
- `$table->index(['entity_type', 'entity_id'])`
- `$table->index(['status', 'next_retry_at'])`

### message_logs

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| outbound_message_id | `foreignId` | nullable, constrained('outbound_messages'), nullOnDelete |
| channel_type | `string(30)` | email, sms, whatsapp, push_notification |
| provider | `string(50)` | nullable |
| recipient | `string` | nullable — email address or phone number |
| subject | `string` | nullable |
| body | `text` | nullable |
| status | `string(20)` | default('pending') — pending, sent, delivered, failed, bounced |
| provider_message_id | `string` | nullable |
| provider_response | `json` | nullable |
| error_message | `text` | nullable |
| sent_at | `timestamp` | nullable |
| delivered_at | `timestamp` | nullable |
| opened_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'status'])`
- `$table->index(['organization_id', 'channel_type'])`
- `$table->index('outbound_message_id')`
- `$table->index('sent_at')`

### message_queues

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| outbound_message_id | `foreignId` | nullable, constrained('outbound_messages'), nullOnDelete |
| channel_type | `string(30)` | email, sms, whatsapp, push_notification |
| provider | `string(50)` | nullable |
| recipient | `string` | nullable |
| subject | `string` | nullable |
| body | `text` | nullable |
| payload | `json` | nullable |
| status | `string(20)` | default('queued') — queued, processing, sent, failed |
| attempts | `unsignedTinyInteger` | default(0) |
| max_attempts | `unsignedTinyInteger` | default(3) |
| last_error | `text` | nullable |
| scheduled_at | `timestamp` | nullable |
| processed_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'status'])`
- `$table->index(['status', 'scheduled_at'])`
- `$table->index('outbound_message_id')`

### outbound_message_attachments

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| message_id | `foreignId` | constrained('outbound_messages'), cascadeOnDelete |
| file_name | `string` |  |
| file_path | `string` |  |
| file_type | `string(50)` |  |
| file_size | `unsignedInteger` |  |
| is_inline | `boolean` | default(false) — Inline image in HTML email |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['message_id'])`

## 0310_purchase_2.php

### contracts

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| contract_number | `string(30)` |  |
| contract_type | `enum(['sales', 'purchase', 'service', 'maintenance'])` |  |
| contact_id | `unsignedBigInteger` |  |
| title | `string(200)` |  |
| start_date | `date` |  |
| end_date | `date` |  |
| auto_renew | `boolean` | default(false) |
| renewal_notice_days | `unsignedSmallInteger` | default(30) |
| currency_code | `string(3)` |  |
| total_value | `decimal(15, 4)` | nullable |
| billed_amount | `decimal(15, 4)` | default(0) |
| status | `enum(['draft', 'active', 'expired', 'terminated', 'cancelled'])` | default('draft') |
| signed_date | `date` | nullable |
| notes | `text` | nullable |
| created_by | `unsignedBigInteger` |  |
| branch_id | `unsignedBigInteger` | nullable |
| parent_contract_id | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'contract_number'], 'contracts_org_number_unique')`
- `$table->index(['organization_id', 'status'], 'contracts_org_status_idx')`
- `$table->index(['organization_id', 'end_date'], 'contracts_org_end_date_idx')`

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade')`
- `$table->foreign('contact_id')->references('id')->on('contacts')`
- `$table->foreign('created_by')->references('id')->on('users')`
- `$table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete()`
- `$table->foreign('parent_contract_id')->references('id')->on('contracts')->nullOnDelete()`

### contract_documents

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| contract_id | `unsignedBigInteger` |  |
| document_type | `string(50)` |  |
| file_path | `string(500)` |  |
| uploaded_by | `unsignedBigInteger` |  |
| uploaded_at | `timestamp` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('contract_id', 'contract_documents_contract_id_idx')`

Foreign keys:

- `$table->foreign('contract_id')->references('id')->on('contracts')->onDelete('cascade')`
- `$table->foreign('uploaded_by')->references('id')->on('users')`

### contract_milestones

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| contract_id | `unsignedBigInteger` |  |
| milestone_name | `string(200)` |  |
| due_date | `date` |  |
| amount | `decimal(15, 4)` |  |
| status | `enum(['pending', 'invoiced', 'paid'])` | default('pending') |
| invoice_id | `unsignedBigInteger` | nullable |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['contract_id', 'status'], 'contract_milestones_contract_status_idx')`
- `$table->index(['contract_id', 'due_date'], 'contract_milestones_contract_due_idx')`

Foreign keys:

- `$table->foreign('contract_id')->references('id')->on('contracts')->onDelete('cascade')`

### contract_releases

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| contract_id | `unsignedBigInteger` |  |
| source_type | `string(50)` | nullable |
| source_id | `unsignedBigInteger` | nullable |
| release_date | `date` |  |
| amount | `decimal(15, 4)` |  |
| status | `enum(['pending', 'fulfilled', 'cancelled'])` | default('pending') |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['contract_id', 'status'], 'contract_releases_contract_status_idx')`

Foreign keys:

- `$table->foreign('contract_id')->references('id')->on('contracts')->onDelete('cascade')`

### ers_configurations

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| vendor_id | `unsignedBigInteger` |  |
| is_enabled | `boolean` | default(true) |
| auto_post | `boolean` | default(false) |
| tolerance_percent | `decimal(5, 2)` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'vendor_id'], 'ers_config_org_vendor_unq')`

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')`
- `$table->foreign('vendor_id', 'ers_config_vendor_fk')->references('id')->on('contacts')`

### outline_agreements

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| vendor_id | `unsignedBigInteger` |  |
| agreement_number | `string(50)` |  |
| agreement_type | `enum(['quantity_contract', 'value_contract', 'scheduling_agreement'])` | default('quantity_contract') |
| status | `enum(['draft', 'active', 'expired', 'cancelled'])` | default('draft') |
| valid_from | `date` |  |
| valid_to | `date` | nullable |
| currency_code | `char(3)` | default('SAR') |
| target_quantity | `decimal(18, 4)` | nullable |
| target_value | `decimal(18, 4)` | nullable |
| released_quantity | `decimal(18, 4)` | default(0) |
| released_value | `decimal(18, 4)` | default(0) |
| payment_terms | `string(100)` | nullable |
| delivery_days | `unsignedSmallInteger` | nullable |
| notes | `text` | nullable |
| created_by | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'agreement_number'], 'oa_org_number_unq')`
- `$table->index(['organization_id', 'vendor_id', 'status'], 'oa_org_vendor_status_idx')`

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')`
- `$table->foreign('vendor_id', 'oa_vendor_fk')->references('id')->on('contacts')`
- `$table->foreign('created_by', 'oa_created_by_fk')->references('id')->on('users')`

### payments_made

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| branch_id | `foreignId` | nullable, constrained, nullOnDelete |
| payment_number | `string(50)` |  |
| payment_date | `date` |  |
| supplier_id | `foreignId` | constrained('contacts') |
| bank_account_id | `foreignId` | nullable, constrained('bank_accounts'), nullOnDelete |
| payment_method | `enum(['cash', 'bank_transfer', 'cheque', 'credit_card', 'online', 'other', ])` | default('bank_transfer') |
| amount | `decimal(18, 4)` |  |
| currency_code | `string(3)` | default('SAR') |
| exchange_rate | `decimal(18, 8)` | default(1) |
| base_amount | `decimal(18, 4)` |  |
| reference | `string(100)` | nullable — Cheque number, transaction ID |
| notes | `text` | nullable |
| status | `enum(['pending', 'completed', 'voided', 'bounced', ])` | default('pending') |
| journal_entry_id | `foreignId` | nullable, constrained('journal_entries'), nullOnDelete |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| approved_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| approved_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'payment_number'])`
- `$table->index(['organization_id', 'supplier_id'])`
- `$table->index(['organization_id', 'payment_date'])`
- `$table->index(['organization_id', 'status'])`

### purchase_orders

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| branch_id | `foreignId` | nullable, constrained, nullOnDelete |
| order_number | `string(50)` |  |
| supplier_id | `foreignId` | constrained('contacts') |
| supplier_name | `string(200)` |  |
| supplier_email | `string(100)` | nullable |
| supplier_address | `text` | nullable |
| warehouse_id | `foreignId` | nullable, constrained, nullOnDelete |
| delivery_address | `text` | nullable |
| order_date | `date` |  |
| expected_delivery_date | `date` | nullable |
| delivery_date | `date` | nullable |
| currency_code | `string(3)` | default('SAR') |
| exchange_rate | `decimal(18, 8)` | default(1) |
| subtotal | `decimal(18, 4)` | default(0) |
| discount_type | `enum(['percentage', 'fixed'])` | nullable |
| discount_value | `decimal(18, 4)` | default(0) |
| discount_amount | `decimal(18, 4)` | default(0) |
| tax_amount | `decimal(18, 4)` | default(0) |
| total | `decimal(18, 4)` | default(0) |
| status | `enum(['draft', 'sent', 'confirmed', 'partially_received', 'received', 'billed', 'cancelled', ])` | default('draft') |
| requested_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| approved_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| approved_at | `timestamp` | nullable |
| notes | `text` | nullable |
| terms_and_conditions | `text` | nullable |
| reference | `string(100)` | nullable — Supplier quote reference |
| version | `unsignedInteger` | default(1) |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |
| incoterm | `string(10)` | nullable |
| port_of_loading | `string` | nullable |
| port_of_discharge | `string` | nullable |
| country_of_origin | `string(3)` | nullable |
| is_international | `boolean` | default(false) |

Indexes:

- `$table->unique(['organization_id', 'order_number'])`
- `$table->index(['organization_id', 'supplier_id'])`
- `$table->index(['organization_id', 'status'])`
- `$table->index(['organization_id', 'order_date'], 'po_org_order_date_idx')`
- `$table->index(['organization_id', 'expected_delivery_date'], 'po_org_exp_delivery_idx')`

Added by later migrations:

- `0530_purchase_order_pending_approval_status.php`: `$table->enum('status', [...self::STATUSES, 'pending_approval'])->default('draft')->change()`
- `0530_purchase_order_pending_approval_status.php`: `$table->enum('discount_type', ['percentage', 'fixed'])->nullable()->change()`

### bills

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| branch_id | `foreignId` | nullable, constrained, nullOnDelete |
| bill_number | `string(50)` | Internal reference |
| supplier_invoice_number | `string(100)` | nullable — Supplier's invoice number |
| bill_type | `enum(['standard', 'debit_note', 'credit_note', ])` | default('standard') |
| purchase_order_id | `foreignId` | nullable, constrained('purchase_orders'), nullOnDelete |
| original_bill_id | `foreignId` | nullable, constrained('bills'), nullOnDelete |
| supplier_id | `foreignId` | constrained('contacts') |
| supplier_name | `string(200)` |  |
| supplier_tax_number | `string(50)` | nullable |
| supplier_address | `text` | nullable |
| bill_date | `date` |  |
| due_date | `date` |  |
| received_date | `date` | nullable — Date goods/services received |
| currency_code | `string(3)` | default('SAR') |
| exchange_rate | `decimal(18, 8)` | default(1) |
| subtotal | `decimal(18, 4)` | default(0) |
| discount_type | `enum(['percentage', 'fixed'])` | nullable |
| discount_value | `decimal(18, 4)` | default(0) |
| discount_amount | `decimal(18, 4)` | default(0) |
| tax_amount | `decimal(18, 4)` | default(0) |
| total | `decimal(18, 4)` | default(0) |
| base_total | `decimal(18, 4)` | default(0) |
| amount_paid | `decimal(18, 4)` | default(0) |
| amount_due | `decimal(18, 4)` | default(0) |
| status | `enum(['draft', 'pending', 'approved', 'partial', 'paid', 'voided', 'overdue'])` | default('draft') |
| place_of_supply | `string(2)` | nullable |
| is_reverse_charge | `boolean` | default(false) |
| journal_entry_id | `foreignId` | nullable, constrained('journal_entries'), nullOnDelete |
| notes | `text` | nullable |
| version | `unsignedInteger` | default(1) |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| approved_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| approved_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |
| reference | `string(100)` | nullable |

Indexes:

- `$table->unique(['organization_id', 'bill_number'])`
- `$table->index(['organization_id', 'supplier_id'])`
- `$table->index(['organization_id', 'status'])`
- `$table->index(['organization_id', 'due_date', 'status'])`

### bill_payment_allocations

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| payment_made_id | `foreignId` | constrained('payments_made'), cascadeOnDelete |
| bill_id | `foreignId` | constrained, cascadeOnDelete |
| amount | `decimal(18, 4)` |  |
| base_amount | `decimal(18, 4)` |  |
| allocated_at | `timestamp` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['payment_made_id', 'bill_id'])`
- `$table->index('bill_id')`

### procurement_goods_receipts

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| gr_number | `string(30)` | nullable |
| purchase_order_id | `foreignId` | nullable, constrained('purchase_orders'), nullOnDelete |
| warehouse_id | `foreignId` | nullable, constrained('warehouses'), nullOnDelete |
| received_at | `timestamp` | nullable |
| status | `enum(['draft', 'confirmed', 'quality_hold'])` | default('draft') |
| supplier_reference | `string(100)` | nullable |
| notes | `text` | nullable |
| received_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| branch_id | `foreignId` | nullable, constrained('branches'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'status'], 'proc_gr_org_status_idx')`
- `$table->index(['organization_id', 'purchase_order_id'], 'proc_gr_org_po_idx')`

### rfq_vendors

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| rfq_id | `unsignedBigInteger` |  |
| contact_id | `unsignedBigInteger` |  |
| sent_at | `timestamp` | nullable |
| response_deadline | `date` | nullable |
| status | `enum(['invited', 'responded', 'declined', 'awarded', 'rejected'])` | default('invited') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['rfq_id', 'contact_id'], 'rfq_vendors_rfq_contact_unique')`
- `$table->index(['rfq_id', 'status'], 'rfq_vendors_rfq_status_idx')`

Foreign keys:

- `$table->foreign('rfq_id')->references('id')->on('rfq_headers')->onDelete('cascade')`
- `$table->foreign('contact_id')->references('id')->on('contacts')`

### rfq_quotes

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| rfq_id | `unsignedBigInteger` |  |
| rfq_vendor_id | `unsignedBigInteger` |  |
| contact_id | `unsignedBigInteger` |  |
| quote_number | `string(100)` | nullable |
| quote_date | `date` | nullable |
| valid_until | `date` | nullable |
| currency_code | `string(3)` |  |
| total_amount | `decimal(15, 4)` |  |
| delivery_days | `unsignedSmallInteger` | nullable |
| payment_terms | `string(200)` | nullable |
| notes | `text` | nullable |
| status | `enum(['received', 'evaluated', 'awarded', 'rejected'])` | default('received') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['rfq_id', 'status'], 'rfq_quotes_rfq_status_idx')`

Foreign keys:

- `$table->foreign('rfq_id')->references('id')->on('rfq_headers')->onDelete('cascade')`
- `$table->foreign('rfq_vendor_id')->references('id')->on('rfq_vendors')->onDelete('cascade')`
- `$table->foreign('contact_id')->references('id')->on('contacts')`

### service_purchase_orders

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| po_number | `string` | unique |
| vendor_id | `unsignedBigInteger` |  |
| description | `text` |  |
| total_value | `decimal(18, 4)` |  |
| currency | `char(3)` | default('SAR') |
| status | `enum(['draft', 'sent', 'partially_accepted', 'accepted', 'closed', 'cancelled'])` | default('draft') |
| valid_from | `date` | nullable |
| valid_to | `date` | nullable |
| created_by | `unsignedBigInteger` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')`
- `$table->foreign('vendor_id')->references('id')->on('contacts')`
- `$table->foreign('created_by')->references('id')->on('users')`

### service_entry_sheets

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| ses_number | `string` | unique |
| service_purchase_order_id | `unsignedBigInteger` |  |
| vendor_id | `unsignedBigInteger` |  |
| service_period_from | `date` |  |
| service_period_to | `date` |  |
| description | `text` |  |
| status | `enum(['draft', 'submitted', 'approved', 'rejected', 'posted'])` | default('draft') |
| submitted_by | `unsignedBigInteger` | nullable |
| approved_by | `unsignedBigInteger` | nullable |
| approved_at | `timestamp` | nullable |
| posted_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')`
- `$table->foreign('service_purchase_order_id', 'ses_po_fk')->references('id')->on('service_purchase_orders')`
- `$table->foreign('vendor_id')->references('id')->on('contacts')`
- `$table->foreign('submitted_by')->references('id')->on('users')`
- `$table->foreign('approved_by')->references('id')->on('users')`

### service_acceptances

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| service_entry_sheet_id | `unsignedBigInteger` | unique |
| accepted_by | `unsignedBigInteger` |  |
| accepted_at | `timestamp` |  |
| rejection_reason | `text` | nullable |
| status | `enum(['accepted', 'rejected'])` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')`
- `$table->foreign('service_entry_sheet_id', 'svc_acc_ses_fk')->references('id')->on('service_entry_sheets')`
- `$table->foreign('accepted_by')->references('id')->on('users')`

### service_po_lines

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| service_purchase_order_id | `unsignedBigInteger` |  |
| line_number | `unsignedSmallInteger` |  |
| service_description | `text` |  |
| service_number | `string` | nullable |
| quantity | `decimal(18, 4)` |  |
| uom | `string(20)` |  |
| unit_price | `decimal(18, 4)` |  |
| total_price | `decimal(18, 4)` |  |
| cost_center_id | `unsignedBigInteger` | nullable |
| internal_order_id | `unsignedBigInteger` | nullable |
| accepted_quantity | `decimal(18, 4)` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('service_purchase_order_id', 'svc_po_line_po_fk')->references('id')->on('service_purchase_orders')->cascadeOnDelete()`
- `$table->foreign('cost_center_id', 'svc_po_line_cc_fk')->references('id')->on('cost_centers')->nullOnDelete()`
- `$table->foreign('internal_order_id', 'svc_po_line_io_fk')->references('id')->on('internal_orders')->nullOnDelete()`

### service_entry_sheet_lines

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| service_entry_sheet_id | `unsignedBigInteger` |  |
| service_po_line_id | `unsignedBigInteger` |  |
| actual_quantity | `decimal(18, 4)` |  |
| uom | `string(20)` |  |
| actual_price | `decimal(18, 4)` |  |
| total_amount | `decimal(18, 4)` |  |
| remarks | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('service_entry_sheet_id', 'ses_line_ses_fk')->references('id')->on('service_entry_sheets')->cascadeOnDelete()`
- `$table->foreign('service_po_line_id', 'ses_line_pol_fk')->references('id')->on('service_po_lines')`

### supplier_credits

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| supplier_id | `foreignId` | constrained('contacts') |
| source_type | `enum(['advance_payment', 'credit_note', 'overpayment', 'adjustment', ])` |  |
| source_id | `unsignedBigInteger` | nullable |
| original_amount | `decimal(18, 4)` |  |
| remaining_amount | `decimal(18, 4)` |  |
| currency_code | `string(3)` | default('SAR') |
| credit_date | `date` |  |
| notes | `text` | nullable |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'supplier_id'])`

### supplier_delivery_records

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| purchase_order_id | `foreignId` | constrained('purchase_orders'), cascadeOnDelete |
| supplier_id | `foreignId` | constrained('contacts'), cascadeOnDelete |
| promised_date | `date` |  |
| actual_date | `date` | nullable |
| quantity_ordered | `decimal(12, 4)` |  |
| quantity_received | `decimal(12, 4)` | default(0) |
| is_on_time | `boolean` | nullable |
| is_complete | `boolean` | nullable |
| quality_accepted | `boolean` | nullable |
| defect_quantity | `decimal(12, 4)` | default(0) |
| notes | `string` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('supplier_id')`
- `$table->index(['organization_id', 'supplier_id'])`

### supplier_incidents

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| supplier_id | `foreignId` | constrained('contacts'), cascadeOnDelete |
| incident_type | `enum(['late_delivery', 'quality_issue', 'pricing_dispute', 'compliance_breach', 'communication'])` | default('quality_issue') |
| severity | `enum(['low', 'medium', 'high', 'critical'])` | default('medium') |
| description | `text` |  |
| occurred_at | `date` |  |
| resolved_at | `date` | nullable |
| resolution_notes | `text` | nullable |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'supplier_id'])`

### supplier_scorecards

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| supplier_id | `foreignId` | constrained('contacts'), cascadeOnDelete |
| evaluation_period_start | `date` |  |
| evaluation_period_end | `date` |  |
| overall_score | `decimal(5, 2)` | nullable |
| quality_score | `decimal(5, 2)` | nullable |
| delivery_score | `decimal(5, 2)` | nullable |
| price_score | `decimal(5, 2)` | nullable |
| service_score | `decimal(5, 2)` | nullable |
| compliance_score | `decimal(5, 2)` | nullable |
| status | `enum(['draft', 'finalized'])` | default('draft') |
| notes | `text` | nullable |
| evaluated_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| finalized_at | `timestamp` | nullable |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['supplier_id', 'evaluation_period_start', 'evaluation_period_end'], 'unique_supplier_scorecard_period')`
- `$table->index('organization_id')`

### supplier_scorecard_ratings

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| scorecard_id | `foreignId` | constrained('supplier_scorecards'), cascadeOnDelete |
| criterion_id | `foreignId` | constrained('supplier_evaluation_criteria'), cascadeOnDelete |
| score | `decimal(5, 2)` |  |
| comments | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

### vendor_advance_requests

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| request_number | `string(30)` |  |
| contact_id | `unsignedBigInteger` |  |
| purchase_order_id | `unsignedBigInteger` | nullable |
| requested_amount | `decimal(15, 4)` |  |
| currency_code | `string(3)` |  |
| exchange_rate | `decimal(10, 6)` | default(1) |
| purpose | `text` | nullable |
| requested_by | `unsignedBigInteger` |  |
| status | `enum(['draft', 'approved', 'paid', 'cleared', 'cancelled'])` | default('draft') |
| approved_by | `unsignedBigInteger` | nullable |
| approved_at | `timestamp` | nullable |
| notes | `text` | nullable |
| branch_id | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'request_number'], 'vendor_adv_req_org_number_unique')`
- `$table->index(['organization_id', 'status'], 'vendor_adv_req_org_status_idx')`
- `$table->index(['organization_id', 'contact_id'], 'vendor_adv_req_org_contact_idx')`

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade')`
- `$table->foreign('contact_id')->references('id')->on('contacts')`
- `$table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->nullOnDelete()`
- `$table->foreign('requested_by')->references('id')->on('users')`
- `$table->foreign('approved_by')->references('id')->on('users')->nullOnDelete()`
- `$table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete()`

### vendor_advance_payments

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| advance_request_id | `unsignedBigInteger` |  |
| payment_date | `date` |  |
| amount | `decimal(15, 4)` |  |
| payment_method | `string(50)` |  |
| bank_account_id | `unsignedBigInteger` | nullable |
| journal_entry_id | `unsignedBigInteger` | nullable |
| reference | `string(100)` | nullable |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('advance_request_id', 'vendor_adv_pay_req_id_idx')`

Foreign keys:

- `$table->foreign('advance_request_id')->references('id')->on('vendor_advance_requests')->onDelete('cascade')`
- `$table->foreign('bank_account_id')->references('id')->on('chart_of_accounts')->nullOnDelete()`

### vendor_advance_clearings

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| advance_payment_id | `unsignedBigInteger` |  |
| bill_id | `unsignedBigInteger` |  |
| cleared_amount | `decimal(15, 4)` |  |
| clearing_date | `date` |  |
| journal_entry_id | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('advance_payment_id', 'vendor_adv_clr_payment_id_idx')`
- `$table->index('bill_id', 'vendor_adv_clr_bill_id_idx')`

Foreign keys:

- `$table->foreign('advance_payment_id')->references('id')->on('vendor_advance_payments')->onDelete('cascade')`
- `$table->foreign('bill_id')->references('id')->on('bills')`

### vendor_advances

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| advance_number | `string(30)` | nullable |
| contact_id | `foreignId` | constrained('contacts') |
| purchase_order_id | `foreignId` | nullable, constrained('purchase_orders'), nullOnDelete |
| amount | `decimal(15, 2)` |  |
| adjusted_amount | `decimal(15, 2)` | default(0) |
| remaining_amount | `decimal(15, 2)` | default(0) |
| currency_code | `string(3)` | default('SAR') |
| exchange_rate | `decimal(10, 6)` | default(1) |
| payment_date | `date` |  |
| payment_method | `string(50)` | nullable |
| reference | `string(100)` | nullable |
| status | `enum(['paid', 'partially_adjusted', 'fully_adjusted', 'refunded'])` | default('paid') |
| notes | `text` | nullable |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| branch_id | `foreignId` | nullable, constrained('branches'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'contact_id'], 'vendor_adv_org_contact_idx')`
- `$table->index(['organization_id', 'status'], 'vendor_adv_org_status_idx')`

### vendor_advance_adjustments

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| vendor_advance_id | `foreignId` | constrained('vendor_advances'), cascadeOnDelete |
| bill_id | `foreignId` | nullable, constrained('bills'), nullOnDelete |
| adjusted_amount | `decimal(15, 2)` |  |
| adjusted_at | `timestamp` | nullable |
| notes | `text` | nullable |
| adjusted_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('vendor_advance_id', 'vendor_adv_adj_advance_id_idx')`
- `$table->index('bill_id', 'vendor_adv_adj_bill_id_idx')`

### vendor_consignment_settlements

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string` | unique |
| organization_id | `unsignedBigInteger` |  |
| vendor_id | `unsignedBigInteger` |  |
| settlement_period_from | `date` |  |
| settlement_period_to | `date` |  |
| total_quantity | `decimal(18, 4)` |  |
| total_value | `decimal(18, 4)` |  |
| currency_code | `string(3)` |  |
| status | `string(20)` | default('draft') — draft/submitted/paid |
| bill_id | `unsignedBigInteger` | nullable |
| settled_at | `dateTime` | nullable |
| settled_by | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['status', 'settlement_period_from'], 'vcse_status_period_idx')`
- `$table->index(['organization_id', 'vendor_id'], 'vcse_org_vendor_idx')`

Foreign keys:

- `$table->foreign('organization_id', 'vcse_org_fk')->references('id')->on('organizations')->onDelete('cascade')`
- `$table->foreign('vendor_id', 'vcse_vendor_fk')->references('id')->on('contacts')->onDelete('cascade')`
- `$table->foreign('bill_id', 'vcse_bill_fk')->references('id')->on('bills')->onDelete('set null')`
- `$table->foreign('settled_by', 'vcse_settled_by_fk')->references('id')->on('users')->onDelete('set null')`

### vendor_contracts

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| contract_number | `string(50)` | nullable |
| contact_id | `foreignId` | constrained('contacts') |
| title | `string(200)` | nullable |
| contract_type | `enum(['supply', 'service', 'framework', 'blanket_order'])` | default('supply') |
| status | `enum(['draft', 'active', 'expired', 'terminated'])` | default('draft') |
| start_date | `date` |  |
| end_date | `date` | nullable |
| signed_at | `date` | nullable |
| terminated_at | `date` | nullable |
| auto_renew | `boolean` | default(false) |
| total_value | `decimal(15, 2)` | default(0) |
| currency_code | `string(3)` | default('SAR') |
| payment_terms | `string(200)` | nullable |
| notes | `text` | nullable |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| branch_id | `foreignId` | nullable, constrained('branches'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'status'], 'vendor_contracts_org_status_idx')`
- `$table->index(['organization_id', 'contact_id'], 'vendor_contracts_org_contact_idx')`

### vendor_credit_notes

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| credit_note_number | `string(100)` |  |
| vendor_id | `unsignedBigInteger` |  |
| bill_id | `unsignedBigInteger` | nullable |
| issue_date | `date` |  |
| credit_date | `date` |  |
| status | `enum(['draft', 'posted', 'applied', 'void'])` | default('draft') |
| reason | `string(255)` | nullable |
| subtotal | `decimal(15, 4)` | default(0) |
| tax_amount | `decimal(15, 4)` | default(0) |
| total_amount | `decimal(15, 4)` | default(0) |
| applied_amount | `decimal(15, 4)` | default(0) |
| notes | `text` | nullable |
| posted_by | `unsignedBigInteger` | nullable |
| posted_at | `timestamp` | nullable |
| voided_by | `unsignedBigInteger` | nullable |
| voided_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'vendor_id', 'status'])`
- `$table->index(['organization_id', 'credit_date'])`

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade')`
- `$table->foreign('vendor_id')->references('id')->on('contacts')`
- `$table->foreign('bill_id')->references('id')->on('bills')->nullOnDelete()`
- `$table->foreign('posted_by')->references('id')->on('users')->nullOnDelete()`
- `$table->foreign('voided_by')->references('id')->on('users')->nullOnDelete()`

### expenses

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| branch_id | `foreignId` | nullable, constrained, nullOnDelete |
| expense_number | `string(30)` |  |
| category_id | `foreignId` | constrained('expense_categories'), cascadeOnDelete |
| employee_id | `foreignId` | nullable, constrained('employees'), nullOnDelete |
| supplier_id | `foreignId` | nullable, constrained('contacts'), nullOnDelete |
| expense_date | `date` |  |
| due_date | `date` | nullable |
| payment_method | `string(30)` | nullable — cash, card, bank_transfer, petty_cash |
| reference | `string` | nullable |
| description | `text` |  |
| currency_code | `string(3)` | default('SAR') |
| exchange_rate | `decimal(10, 6)` | default(1) |
| amount | `decimal(15, 2)` |  |
| tax_amount | `decimal(15, 2)` | default(0) |
| total_amount | `decimal(15, 2)` |  |
| base_amount | `decimal(15, 2)` | In base currency |
| status | `string(20)` | default('draft') — draft, submitted, approved, rejected, paid, cancelled |
| is_reimbursable | `boolean` | default(false) |
| is_recurring | `boolean` | default(false) |
| recurring_expense_id | `foreignId` | nullable — Parent recurring expense |
| is_billable | `boolean` | default(false) |
| customer_id | `foreignId` | nullable, constrained('contacts'), nullOnDelete |
| account_id | `foreignId` | nullable, constrained('chart_of_accounts'), nullOnDelete |
| bank_account_id | `foreignId` | nullable, constrained('bank_accounts'), nullOnDelete |
| journal_entry_id | `foreignId` | nullable, constrained('journal_entries'), nullOnDelete |
| bill_id | `foreignId` | nullable — Converted to payable |
| notes | `text` | nullable |
| custom_fields | `json` | nullable |
| created_by | `foreignId` | constrained('users'), cascadeOnDelete |
| approved_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| approved_at | `timestamp` | nullable |
| paid_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'expense_date'])`
- `$table->index(['organization_id', 'status'])`
- `$table->index(['organization_id', 'category_id'])`
- `$table->index(['employee_id', 'status'])`

Foreign keys:

- `$table->foreign('bill_id', 'expense_bill_fk')->references('id')->on('bills')->nullOnDelete()`
- `$table->foreign('recurring_expense_id', 'expense_recurring_fk')->references('id')->on('recurring_expenses')->nullOnDelete()`

### expense_items

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| expense_id | `foreignId` | constrained, cascadeOnDelete |
| category_id | `foreignId` | nullable, constrained('expense_categories'), nullOnDelete |
| description | `text` |  |
| amount | `decimal(15, 2)` |  |
| tax_rate | `decimal(5, 2)` | default(0) |
| tax_amount | `decimal(15, 2)` | default(0) |
| total_amount | `decimal(15, 2)` |  |
| account_id | `foreignId` | nullable, constrained('chart_of_accounts'), nullOnDelete |
| line_order | `unsignedTinyInteger` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

### expense_receipts

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| expense_id | `foreignId` | constrained, cascadeOnDelete |
| file_name | `string` |  |
| file_path | `string` |  |
| mime_type | `string(100)` |  |
| file_size | `unsignedBigInteger` |  |
| ocr_text | `text` | nullable — Extracted text from receipt |
| ocr_data | `json` | nullable — Parsed OCR data (amount, date, vendor) |
| uploaded_by | `foreignId` | constrained('users'), cascadeOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

### expense_report_items

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| report_id | `foreignId` | constrained('expense_reports'), cascadeOnDelete |
| expense_id | `foreignId` | constrained, cascadeOnDelete |
| approved_amount | `decimal` | nullable |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['report_id', 'expense_id'])`

### subcontract_orders

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| order_number | `string(30)` |  |
| contact_id | `unsignedBigInteger` |  |
| status | `enum(['draft', 'sent', 'material_transferred', 'in_process', 'received', 'closed', 'cancelled'])` | default('draft') |
| issued_date | `date` | nullable |
| expected_receipt_date | `date` | nullable |
| currency_code | `string(3)` | default('USD') |
| service_charge | `decimal(15, 4)` | default(0) |
| notes | `text` | nullable |
| purchase_order_id | `unsignedBigInteger` | nullable |
| created_by | `unsignedBigInteger` |  |
| branch_id | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'order_number'], 'sco_org_number_unique')`
- `$table->index(['organization_id', 'status'], 'sco_org_status_idx')`

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade')`
- `$table->foreign('contact_id')->references('id')->on('contacts')->onDelete('restrict')`
- `$table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->onDelete('set null')`
- `$table->foreign('created_by')->references('id')->on('users')`
- `$table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null')`

### subcontract_receipts

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| order_id | `unsignedBigInteger` |  |
| receipt_date | `date` |  |
| warehouse_id | `unsignedBigInteger` |  |
| status | `enum(['draft', 'posted'])` | default('draft') |
| journal_entry_id | `unsignedBigInteger` | nullable |
| notes | `text` | nullable |
| created_by | `unsignedBigInteger` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('order_id', 'scr_order_idx')`

Foreign keys:

- `$table->foreign('order_id')->references('id')->on('subcontract_orders')->onDelete('cascade')`
- `$table->foreign('warehouse_id')->references('id')->on('warehouses')`
- `$table->foreign('journal_entry_id')->references('id')->on('journal_entries')->onDelete('set null')`
- `$table->foreign('created_by')->references('id')->on('users')`

## 0320_sales_3.php

### credit_notes

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| branch_id | `foreignId` | nullable, constrained, nullOnDelete |
| credit_note_number | `string(30)` | nullable |
| credit_note_type | `string(20)` | default('sales') — sales, purchase |
| invoice_id | `foreignId` | nullable, constrained('invoices'), nullOnDelete |
| bill_id | `foreignId` | nullable |
| contact_id | `foreignId` | constrained('contacts'), cascadeOnDelete |
| contact_name | `string` | nullable |
| contact_tax_number | `string` | nullable |
| credit_note_date | `date` | nullable |
| original_invoice_date | `date` | nullable |
| currency_code | `string(3)` | default('SAR') |
| exchange_rate | `decimal(10, 6)` | default(1) |
| subtotal | `decimal(15, 2)` | default(0) |
| discount_amount | `decimal(15, 2)` | default(0) |
| tax_amount | `decimal(15, 2)` | default(0) |
| total | `decimal(15, 2)` | default(0) |
| base_total | `decimal(15, 2)` | default(0) |
| applied_amount | `decimal(15, 2)` | default(0) |
| refunded_amount | `decimal(15, 2)` | default(0) |
| available_amount | `decimal(15, 2)` |  |
| reason_code | `string(30)` | nullable — return, discount, error, damaged, etc. |
| reason | `text` | nullable |
| status | `string(20)` | default('draft') — draft, approved, applied, refunded, cancelled |
| compliance_status | `string(30)` | nullable |
| compliance_uuid | `string` | nullable |
| journal_entry_id | `foreignId` | nullable, constrained('journal_entries'), nullOnDelete |
| wallet_transaction_id | `foreignId` | nullable |
| notes | `text` | nullable |
| terms_and_conditions | `text` | nullable |
| created_by | `foreignId` | constrained('users'), cascadeOnDelete |
| approved_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| approved_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'credit_note_type', 'status'])`
- `$table->index(['contact_id', 'status'])`
- `$table->index(['invoice_id'])`

Foreign keys:

- `$table->foreign('bill_id', 'credit_note_bill_fk')->references('id')->on('bills')->nullOnDelete()`
- `$table->foreign('wallet_transaction_id', 'credit_note_wallet_txn_fk')->references('id')->on('wallet_transactions')->nullOnDelete()`

### credit_note_applications

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| credit_note_id | `foreignId` | constrained, cascadeOnDelete |
| invoice_id | `foreignId` | nullable, constrained('invoices'), nullOnDelete |
| applied_to_type | `string` | nullable |
| applied_to_id | `unsignedBigInteger` | nullable |
| amount | `decimal(18, 4)` | default(0) |
| applied_amount | `decimal(15, 2)` | default(0) |
| applied_date | `date` | nullable |
| applied_by | `foreignId` | nullable, constrained('users'), cascadeOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `nullableMorphs('applied_to')`

### debit_notes

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| branch_id | `foreignId` | nullable, constrained, nullOnDelete |
| debit_note_number | `string(30)` |  |
| bill_id | `foreignId` | nullable |
| contact_id | `foreignId` | constrained('contacts'), cascadeOnDelete |
| contact_name | `string` |  |
| debit_note_date | `date` |  |
| currency_code | `string(3)` | default('SAR') |
| exchange_rate | `decimal(10, 6)` | default(1) |
| subtotal | `decimal(15, 2)` |  |
| tax_amount | `decimal(15, 2)` | default(0) |
| total | `decimal(15, 2)` |  |
| applied_amount | `decimal(15, 2)` | default(0) |
| available_amount | `decimal(15, 2)` |  |
| reason_code | `string(30)` | nullable |
| reason | `text` | nullable |
| status | `string(20)` | default('draft') |
| journal_entry_id | `foreignId` | nullable, constrained('journal_entries'), nullOnDelete |
| created_by | `foreignId` | constrained('users'), cascadeOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'status'])`
- `$table->index(['contact_id', 'status'])`

Foreign keys:

- `$table->foreign('bill_id', 'debit_note_bill_fk')->references('id')->on('bills')->nullOnDelete()`

### intercompany_purchase_order_links

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| intercompany_sales_order_id | `unsignedBigInteger` |  |
| purchase_order_id | `unsignedBigInteger` | nullable |
| buying_organization_id | `unsignedBigInteger` |  |
| status | `enum(['pending', 'linked', 'cancelled'])` | default('pending') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['intercompany_sales_order_id'], 'icpol_icso_unq')`

Foreign keys:

- `$table->foreign('intercompany_sales_order_id', 'icpol_icso_fk')->references('id')->on('intercompany_sales_orders')->cascadeOnDelete()`
- `$table->foreign('purchase_order_id', 'icpol_po_fk')->references('id')->on('purchase_orders')->nullOnDelete()`
- `$table->foreign('buying_organization_id', 'icpol_buying_org_fk')->references('id')->on('organizations')->restrictOnDelete()`

### purchase_returns

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| branch_id | `foreignId` | nullable, constrained, nullOnDelete |
| return_number | `string(30)` |  |
| supplier_id | `foreignId` | constrained('contacts'), cascadeOnDelete |
| bill_id | `foreignId` | nullable, constrained('bills'), nullOnDelete |
| purchase_order_id | `foreignId` | nullable, constrained('purchase_orders'), nullOnDelete |
| return_date | `date` |  |
| return_reason_id | `foreignId` | nullable, constrained('return_reasons'), nullOnDelete |
| reason_notes | `text` | nullable |
| return_type | `string(20)` | default('debit_note') — debit_note, replacement, refund |
| currency_code | `string(3)` |  |
| subtotal | `decimal(15, 2)` | default(0) |
| tax_amount | `decimal(15, 2)` | default(0) |
| total | `decimal(15, 2)` | default(0) |
| status | `string(30)` | default('draft') — draft, approved, shipped, received_by_supplier, completed, cancelled |
| resolution_type | `string(30)` | nullable — debit_note, replacement, refund |
| debit_note_id | `foreignId` | nullable |
| replacement_po_id | `foreignId` | nullable |
| shipping_method | `string` | nullable |
| tracking_number | `string` | nullable |
| shipped_at | `timestamp` | nullable |
| supplier_received_at | `timestamp` | nullable |
| journal_entry_id | `foreignId` | nullable, constrained('journal_entries'), nullOnDelete |
| approved_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| approved_at | `timestamp` | nullable |
| created_by | `foreignId` | constrained('users'), cascadeOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'return_number'])`
- `$table->index(['organization_id', 'status'])`
- `$table->index(['supplier_id', 'status'])`
- `$table->index(['bill_id'])`

Foreign keys:

- `$table->foreign('debit_note_id', 'pur_ret_debit_note_fk')->references('id')->on('debit_notes')->nullOnDelete()`
- `$table->foreign('replacement_po_id', 'pur_ret_replacement_po_fk')->references('id')->on('purchase_orders')->nullOnDelete()`

### sales_returns

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| branch_id | `foreignId` | nullable, constrained, nullOnDelete |
| return_number | `string(30)` |  |
| customer_id | `foreignId` | constrained('contacts'), cascadeOnDelete |
| invoice_id | `foreignId` | nullable, constrained('invoices'), nullOnDelete |
| sales_order_id | `foreignId` | nullable, constrained('sales_orders'), nullOnDelete |
| return_date | `date` |  |
| return_reason_id | `foreignId` | nullable, constrained('return_reasons'), nullOnDelete |
| reason_notes | `text` | nullable |
| return_type | `string(20)` | default('refund') — refund, exchange, credit_note, replacement |
| currency_code | `string(3)` |  |
| subtotal | `decimal(15, 2)` | default(0) |
| tax_amount | `decimal(15, 2)` | default(0) |
| restocking_fee | `decimal(15, 2)` | default(0) |
| total | `decimal(15, 2)` | default(0) |
| refund_amount | `decimal(15, 2)` | default(0) |
| status | `string(30)` | default('pending') — pending, approved, received, inspected, completed, rejected, cancelled |
| inspection_status | `string(20)` | nullable — pending, passed, failed, partial |
| inspection_notes | `text` | nullable |
| resolution_type | `string(30)` | nullable — full_refund, partial_refund, exchange, credit_note, replacement, rejected |
| credit_note_id | `foreignId` | nullable — Link to generated credit note |
| refund_id | `foreignId` | nullable — Link to refund record |
| exchange_order_id | `foreignId` | nullable — Link to replacement sales order |
| warehouse_id | `foreignId` | nullable, constrained('warehouses'), nullOnDelete |
| restock_items | `boolean` | default(true) |
| items_received | `boolean` | default(false) |
| received_at | `timestamp` | nullable |
| approved_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| approved_at | `timestamp` | nullable |
| rejected_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| rejected_at | `timestamp` | nullable |
| rejection_reason | `text` | nullable |
| journal_entry_id | `foreignId` | nullable, constrained('journal_entries'), nullOnDelete |
| created_by | `foreignId` | constrained('users'), cascadeOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'return_number'])`
- `$table->index(['organization_id', 'status'])`
- `$table->index(['customer_id', 'status'])`
- `$table->index(['invoice_id'])`
- `$table->index(['organization_id', 'return_date'])`

Added by later migrations:

- `0470_deferred_keys.php`: `$table->foreign('credit_note_id', 'sales_ret_credit_note_fk')->references('id')->on('credit_notes')->nullOnDelete()`
- `0470_deferred_keys.php`: `$table->foreign('refund_id', 'sales_ret_refund_fk')->references('id')->on('refunds')->nullOnDelete()`
- `0470_deferred_keys.php`: `$table->foreign('exchange_order_id', 'sales_ret_exchange_order_fk')->references('id')->on('exchange_orders')->nullOnDelete()`

### exchange_orders

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| exchange_number | `string(30)` |  |
| sales_return_id | `foreignId` | constrained('sales_returns'), cascadeOnDelete |
| customer_id | `foreignId` | constrained('contacts'), cascadeOnDelete |
| original_total | `decimal(15, 2)` |  |
| exchange_total | `decimal(15, 2)` |  |
| price_difference | `decimal(15, 2)` | default(0) — Positive = customer pays, negative = refund |
| difference_resolution | `string(30)` | nullable — payment, credit_note, waived |
| new_invoice_id | `foreignId` | nullable, constrained('invoices'), nullOnDelete |
| new_sales_order_id | `foreignId` | nullable, constrained('sales_orders'), nullOnDelete |
| status | `string(30)` | default('pending') — pending, processing, shipped, completed, cancelled |
| notes | `text` | nullable |
| created_by | `foreignId` | constrained('users'), cascadeOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'exchange_number'])`
- `$table->index(['organization_id', 'status'])`
- `$table->index(['customer_id'])`

### refunds

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| branch_id | `foreignId` | nullable, constrained, nullOnDelete |
| refund_number | `string(30)` |  |
| refund_type | `string(20)` | customer_refund, supplier_refund |
| refundable_type | `string` |  |
| refundable_id | `unsignedBigInteger` |  |
| contact_id | `foreignId` | constrained('contacts'), cascadeOnDelete |
| sales_return_id | `unsignedBigInteger` | nullable |
| payment_received_id | `unsignedBigInteger` | nullable |
| refund_date | `date` |  |
| currency_code | `string(3)` | default('SAR') |
| amount | `decimal(15, 2)` |  |
| refund_method | `string(30)` | cash, bank_transfer, original_payment_method |
| bank_account_id | `foreignId` | nullable, constrained('bank_accounts'), nullOnDelete |
| reference | `string` | nullable |
| transaction_reference | `string` | nullable |
| status | `string(20)` | default('pending') — pending, approved, processed, cancelled |
| reason | `text` | nullable |
| notes | `text` | nullable |
| journal_entry_id | `foreignId` | nullable, constrained('journal_entries'), nullOnDelete |
| approved_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| approved_at | `timestamp` | nullable |
| processed_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| processed_at | `timestamp` | nullable |
| created_by | `foreignId` | constrained('users'), cascadeOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `morphs('refundable')`
- `$table->index(['organization_id', 'refund_type', 'status'])`
- `$table->index(['contact_id'])`

Foreign keys:

- `$table->foreign('sales_return_id', 'refund_sales_return_fk')->references('id')->on('sales_returns')->nullOnDelete()`
- `$table->foreign('payment_received_id', 'refund_payment_received_fk')->references('id')->on('payments_received')->nullOnDelete()`

### rma_requests

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| rma_number | `string(30)` |  |
| rma_type | `string(20)` | sales_return, purchase_return |
| customer_id | `foreignId` | nullable, constrained('contacts'), nullOnDelete |
| supplier_id | `foreignId` | nullable, constrained('contacts'), nullOnDelete |
| invoice_id | `foreignId` | nullable, constrained('invoices'), nullOnDelete |
| bill_id | `foreignId` | nullable, constrained('bills'), nullOnDelete |
| description | `text` |  |
| requested_resolution | `string(30)` | refund, exchange, repair, credit |
| status | `string(30)` | default('pending') — pending, approved, in_progress, completed, rejected, expired |
| request_date | `date` |  |
| expiry_date | `date` | nullable — RMA validity period |
| sales_return_id | `foreignId` | nullable, constrained('sales_returns'), nullOnDelete |
| purchase_return_id | `foreignId` | nullable, constrained('purchase_returns'), nullOnDelete |
| approved_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| approved_at | `timestamp` | nullable |
| approval_notes | `text` | nullable |
| created_by | `foreignId` | constrained('users'), cascadeOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'rma_number'])`
- `$table->index(['organization_id', 'status'])`
- `$table->index(['rma_type', 'status'])`

### audit_logs

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | nullable, constrained, cascadeOnDelete |
| user_id | `foreignId` | nullable, constrained, nullOnDelete |
| auditable_type | `string` |  |
| auditable_id | `unsignedBigInteger` |  |
| event | `string(20)` | created, updated, deleted, restored |
| old_values | `json` | nullable |
| new_values | `json` | nullable |
| ip_address | `string(45)` | nullable |
| user_agent | `text` | nullable |
| url | `text` | nullable |
| created_at | `timestamp` | useCurrent |

Indexes:

- `$table->index(['auditable_type', 'auditable_id'])`
- `$table->index('organization_id')`
- `$table->index('user_id')`
- `$table->index('event')`
- `$table->index('created_at')`

### settings

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | nullable, constrained, cascadeOnDelete |
| group | `string(50)` | e.g., 'general', 'invoice', 'email', 'tax' |
| key | `string(100)` |  |
| value | `text` | nullable |
| type | `string(20)` | default('string') — string, integer, boolean, json, array |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'group', 'key'])`
- `$table->index('group')`

## 0340_tax.php

### hsn_sac_codes

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| code | `string(20)` | unique |
| description | `text` |  |
| gst_rate | `decimal(5, 2)` | 0, 5, 12, 18, 28 |
| type | `enum(['goods', 'service'])` | default('goods') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('gst_rate')`
- `$table->index('type')`

### tax_categories

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| name | `string(50)` | e.g., "Standard Rate", "Zero Rated" |
| code | `string(10)` | S, Z, E, O (ZATCA codes) |
| description | `text` | nullable |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'code'])`

### tax_rates

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| tax_category_id | `foreignId` | constrained, cascadeOnDelete |
| name | `string(50)` |  |
| rate | `decimal(5, 2)` | e.g., 15.00 for 15% |
| country_code | `string(2)` |  |
| effective_from | `date` |  |
| effective_to | `date` | nullable |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['tax_category_id', 'country_code', 'effective_from'])`

### tax_determination_rules

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| name | `string(255)` |  |
| description | `text` | nullable |
| document_type | `enum(['sales_invoice', 'purchase_bill', 'sales_order', 'purchase_order', 'all', ])` |  |
| from_country_code | `char(2)` | nullable |
| to_country_code | `char(2)` | nullable |
| from_region | `string(100)` | nullable |
| to_region | `string(100)` | nullable |
| tax_category_id | `foreignId` | nullable, constrained('tax_categories'), nullOnDelete |
| customer_type | `enum(['b2b', 'b2c', 'government', 'exempt', 'any'])` | default('any') |
| tax_type | `enum(['standard', 'zero', 'exempt', 'reverse_charge', 'out_of_scope'])` |  |
| tax_rate_id | `foreignId` | nullable, constrained('tax_rates'), nullOnDelete |
| is_reverse_charge | `boolean` | default(false) |
| priority | `unsignedSmallInteger` | default(100) |
| is_active | `boolean` | default(true) |
| valid_from | `date` | nullable |
| valid_to | `date` | nullable |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'document_type', 'is_active'], 'tax_determination_rules_org_doc_type_active_idx')`
- `$table->index(['organization_id', 'priority'])`

### vat_return_periods

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| country_code | `char(3)` |  |
| period_start | `date` |  |
| period_end | `date` |  |
| status | `enum(['draft', 'ready', 'submitted', 'accepted', 'amended'])` | default('draft') |
| submitted_at | `timestamp` | nullable |
| reference_number | `string(50)` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index('organization_id', 'vat_periods_org_id_idx')`
- `$table->index(['organization_id', 'country_code'], 'vat_periods_org_country_idx')`
- `$table->index(['organization_id', 'status'], 'vat_periods_org_status_idx')`
- `$table->index(['period_start', 'period_end'], 'vat_periods_dates_idx')`

### vat_return_boxes

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| vat_return_period_id | `unsignedBigInteger` |  |
| box_number | `string(10)` |  |
| box_label | `string(200)` |  |
| output_amount | `decimal(15, 4)` | default(0) |
| input_amount | `decimal(15, 4)` | default(0) |
| net_vat | `decimal(15, 4)` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('vat_return_period_id', 'vat_boxes_period_id_idx')`
- `$table->unique(['vat_return_period_id', 'box_number'], 'vat_boxes_period_box_unq')`

Foreign keys:

- `$table->foreign('vat_return_period_id', 'vat_boxes_period_fk')->references('id')->on('vat_return_periods')->onDelete('cascade')`

### vat_transactions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| transaction_type | `enum(['sale', 'purchase', 'credit_note', 'refund', 'return'])` |  |
| source_type | `string(50)` | nullable |
| source_id | `unsignedBigInteger` | nullable |
| tax_period | `date` |  |
| taxable_amount | `decimal(15, 4)` | default(0) |
| vat_amount | `decimal(15, 4)` | default(0) |
| vat_rate | `decimal(5, 2)` | default(0) |
| country_code | `char(3)` |  |
| is_exempt | `boolean` | default(false) |
| is_zero_rated | `boolean` | default(false) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index('organization_id', 'vat_txn_org_id_idx')`
- `$table->index(['organization_id', 'tax_period'], 'vat_txn_org_period_idx')`
- `$table->index(['organization_id', 'transaction_type'], 'vat_txn_org_type_idx')`
- `$table->index(['source_type', 'source_id'], 'vat_txn_source_idx')`
- `$table->index('country_code', 'vat_txn_country_idx')`

## 0350_inventory_2.php

### products

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| sku | `string(50)` |  |
| barcode | `string(50)` | nullable |
| name | `string(200)` |  |
| description | `text` | nullable |
| type | `enum(['goods', 'service'])` | default('goods') |
| category_id | `foreignId` | nullable, constrained, nullOnDelete |
| unit_id | `foreignId` | constrained('units_of_measure') |
| purchase_price | `decimal(18, 4)` | default(0) |
| selling_price | `decimal(18, 4)` | default(0) |
| minimum_price | `decimal(18, 4)` | nullable — Floor price |
| tax_category_id | `foreignId` | nullable, constrained, nullOnDelete |
| hsn_code | `string(20)` | nullable — HSN/SAC for India GST |
| income_account_id | `foreignId` | nullable, constrained('chart_of_accounts'), nullOnDelete |
| expense_account_id | `foreignId` | nullable, constrained('chart_of_accounts'), nullOnDelete |
| inventory_account_id | `foreignId` | nullable, constrained('chart_of_accounts'), nullOnDelete |
| costing_method | `enum(['fifo', 'weighted_average', 'standard'])` | default('weighted_average') |
| track_inventory | `boolean` | default(true) |
| reorder_level | `decimal(18, 4)` | nullable |
| reorder_quantity | `decimal(18, 4)` | nullable |
| weight | `decimal(10, 3)` | nullable |
| weight_unit | `string(10)` | nullable |
| length | `decimal(10, 3)` | nullable |
| width | `decimal(10, 3)` | nullable |
| height | `decimal(10, 3)` | nullable |
| dimension_unit | `string(10)` | nullable |
| image_url | `string(500)` | nullable |
| gallery_urls | `json` | nullable |
| has_variants | `boolean` | default(false) |
| is_active | `boolean` | default(true) |
| is_purchasable | `boolean` | default(true) |
| is_sellable | `boolean` | default(true) |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| updated_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |
| product_type | `string(30)` | default('goods') |
| base_unit_id | `foreignId` | nullable, constrained('units_of_measure'), nullOnDelete |
| track_batches | `boolean` | default(false) |
| track_serials | `boolean` | default(false) |
| has_expiry | `boolean` | default(false) |
| expiry_warning_days | `unsignedSmallInteger` | nullable |
| allow_negative_stock | `boolean` | default(false) |
| sell_below_cost | `boolean` | default(true) |
| minimum_stock | `decimal(15, 4)` | nullable |
| maximum_stock | `decimal(15, 4)` | nullable |
| reorder_point | `decimal(15, 4)` | nullable |
| is_loose_item | `boolean` | default(false) |
| tare_weight | `decimal(10, 4)` | nullable |
| barcode_type | `string(30)` | nullable |
| long_description | `text` | nullable |
| short_description | `text` | nullable |
| brand | `string` | nullable |
| manufacturer | `string` | nullable |
| model_number | `string` | nullable |
| country_of_origin | `string(3)` | nullable |
| mrp | `decimal(15, 4)` | nullable |
| wholesale_price | `decimal(15, 4)` | nullable |
| minimum_order_qty | `decimal(15, 4)` | nullable |
| maximum_order_qty | `decimal(15, 4)` | nullable |
| warranty_type | `string(30)` | nullable |
| warranty_months | `unsignedSmallInteger` | nullable |
| warranty_terms | `text` | nullable |
| shelf_life_days | `string` | nullable |
| seo_meta | `json` | nullable |
| is_featured | `boolean` | default(false) |
| is_new_arrival | `boolean` | default(false) |
| is_bestseller | `boolean` | default(false) |
| is_returnable | `boolean` | default(true) |
| is_taxable | `boolean` | default(true) |
| requires_shipping | `boolean` | default(true) |
| lead_time_days | `unsignedSmallInteger` | default(0), comment('Procurement/manufacturing lead time in days') |
| default_supplier_lead_days | `unsignedSmallInteger` | default(0), comment('Default supplier delivery lead time in days') |
| requires_inspection | `boolean` | default(false) |
| batch_selection_strategy | `string` | nullable, comment('Override batch deduction order: fifo, lifo, fefo. Null falls back to costing_method.') |
| material_account_group_id | `unsignedBigInteger` | nullable |

Indexes:

- `$table->unique(['organization_id', 'sku'])`
- `$table->index(['organization_id', 'barcode'])`
- `$table->index(['organization_id', 'category_id'])`
- `$table->index(['organization_id', 'is_active'])`

Foreign keys:

- `$table->foreign('material_account_group_id', 'product_mag_fk')->references('id')->on('material_account_groups')->onDelete('set null')`

### cross_docking_order_lines

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| cross_docking_order_id | `foreignId` | constrained('cross_docking_orders'), cascadeOnDelete |
| product_id | `foreignId` | constrained('products'), cascadeOnDelete |
| quantity | `decimal(18, 4)` |  |
| unit_id | `foreignId` | nullable, constrained('units_of_measure'), nullOnDelete |
| quantity_transferred | `decimal(18, 4)` | default(0) |
| status | `string(20)` | default('pending'), comment('pending/transferred/partial') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('cross_docking_order_id', 'xdock_line_order_idx')`

### cycle_count_lines

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| cycle_count_session_id | `unsignedBigInteger` |  |
| product_id | `unsignedBigInteger` |  |
| warehouse_location_id | `unsignedBigInteger` | nullable |
| system_quantity | `decimal(18, 4)` |  |
| counted_quantity | `decimal(18, 4)` | nullable |
| variance_percentage | `decimal(8, 4)` | nullable |
| recount_required | `boolean` | default(false) |
| status | `enum(['pending', 'counted', 'recounted', 'approved'])` | default('pending') |
| approved_by | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('cycle_count_session_id', 'cc_line_sess_fk')->references('id')->on('cycle_count_sessions')->cascadeOnDelete()`
- `$table->foreign('product_id', 'cc_line_prod_fk')->references('id')->on('products')->cascadeOnDelete()`
- `$table->foreign('warehouse_location_id', 'cc_line_loc_fk')->references('id')->on('warehouse_locations')->nullOnDelete()`
- `$table->foreign('approved_by', 'cc_line_appr_fk')->references('id')->on('users')->nullOnDelete()`

### ewm_transfer_orders

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| warehouse_id | `foreignId` | constrained('warehouses'), cascadeOnDelete |
| to_number | `string(30)` | unique — TO-2026-001234 |
| movement_type | `enum(['goods_receipt', 'goods_issue', 'internal_move', 'replenishment', 'stock_transfer', 'physical_inventory', ])` |  |
| status | `enum(['created', 'assigned', 'in_progress', 'confirmed', 'cancelled'])` | default('created') |
| source_bin_id | `foreignId` | nullable, constrained('ewm_bins'), nullOnDelete |
| source_bin_code | `string(50)` | nullable |
| dest_bin_id | `foreignId` | nullable, constrained('ewm_bins'), nullOnDelete |
| dest_bin_code | `string(50)` | nullable |
| product_id | `foreignId` | constrained('products'), restrictOnDelete |
| requested_qty | `decimal(15, 4)` |  |
| confirmed_qty | `decimal(15, 4)` | nullable |
| unit_of_measure | `string(20)` | default('EA') |
| batch_number | `string(50)` | nullable |
| serial_number | `string(50)` | nullable |
| reference_type | `string(50)` | nullable — sales_order, purchase_order, etc. |
| reference_id | `unsignedBigInteger` | nullable |
| assigned_to | `foreignId` | nullable, constrained('users'), nullOnDelete |
| assigned_at | `timestamp` | nullable |
| started_at | `timestamp` | nullable |
| confirmed_at | `timestamp` | nullable |
| actual_duration_minutes | `decimal(8, 2)` | nullable |
| created_by | `foreignId` | constrained('users'), restrictOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'warehouse_id', 'status'])`
- `$table->index(['organization_id', 'movement_type', 'status'])`
- `$table->index(['reference_type', 'reference_id'])`

### ewm_labor_tasks

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| warehouse_id | `foreignId` | constrained('warehouses'), cascadeOnDelete |
| transfer_order_id | `foreignId` | nullable, constrained('ewm_transfer_orders'), nullOnDelete |
| task_type | `enum(['pick', 'put', 'move', 'count', 'pack', 'load', 'unload'])` |  |
| priority | `enum(['urgent', 'high', 'normal', 'low'])` | default('normal') |
| status | `enum(['queued', 'assigned', 'in_progress', 'completed', 'cancelled'])` | default('queued') |
| assigned_to | `foreignId` | nullable, constrained('users'), nullOnDelete |
| assigned_at | `timestamp` | nullable |
| started_at | `timestamp` | nullable |
| completed_at | `timestamp` | nullable |
| standard_minutes | `decimal(8, 2)` | nullable — expected task duration |
| actual_minutes | `decimal(8, 2)` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'warehouse_id', 'status', 'priority'], 'ewm_labor_tasks_org_warehouse_status_priority_idx')`
- `$table->index(['assigned_to', 'status'])`

### hazmat_transport_regulations

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string` | unique |
| organization_id | `unsignedBigInteger` |  |
| product_id | `unsignedBigInteger` |  |
| un_number | `string(10)` | nullable |
| proper_shipping_name | `string(200)` | nullable |
| hazard_class | `string(20)` | nullable |
| packing_group | `string(5)` | nullable |
| transport_mode | `string(20)` | road/air/sea/rail |
| is_forbidden | `boolean` | default(false) |
| special_provisions | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['transport_mode', 'product_id'], 'htr_mode_product_idx')`

Foreign keys:

- `$table->foreign('organization_id', 'htr_org_fk')->references('id')->on('organizations')->onDelete('cascade')`
- `$table->foreign('product_id', 'htr_product_fk')->references('id')->on('products')->onDelete('cascade')`

### product_certifications

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| product_id | `foreignId` | constrained('products'), cascadeOnDelete |
| certification_name | `string` | Halal, ISO 9001, Organic, CE, FDA |
| certification_body | `string` | nullable — Issuing authority |
| certificate_number | `string` | nullable |
| issued_date | `date` | nullable |
| expiry_date | `date` | nullable |
| certificate_file_path | `string` | nullable |
| status | `string(20)` | default('active') — active, expired, pending, revoked |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['product_id', 'status'])`
- `$table->index(['expiry_date'])`

### product_documents

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| product_id | `foreignId` | constrained('products'), cascadeOnDelete |
| name | `string` |  |
| file_path | `string` |  |
| file_type | `string(50)` |  |
| file_size | `unsignedInteger` |  |
| document_type | `string(30)` | manual, datasheet, certificate, warranty, safety, brochure |
| language | `string(5)` | default('en') |
| is_public | `boolean` | default(true) — Visible to customers |
| display_order | `unsignedSmallInteger` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['product_id', 'document_type'])`

### product_hazmat_classifications

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| product_id | `unsignedBigInteger` |  |
| hazmat_classification_id | `unsignedBigInteger` |  |
| storage_class_id | `unsignedBigInteger` | nullable |
| is_primary | `boolean` | default(false) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('product_id', 'phc_product_fk')->references('id')->on('products')->onDelete('cascade')`
- `$table->foreign('hazmat_classification_id', 'phc_classification_fk')->references('id')->on('hazmat_classifications')->onDelete('cascade')`
- `$table->foreign('storage_class_id', 'phc_storage_class_fk')->references('id')->on('hazmat_storage_classes')->onDelete('set null')`

### product_relations

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| product_id | `foreignId` | constrained('products'), cascadeOnDelete |
| related_product_id | `foreignId` | constrained('products'), cascadeOnDelete |
| relation_type | `string(20)` | related, cross_sell, up_sell, accessory, spare_part, substitute, frequently_bought |
| display_order | `unsignedSmallInteger` | default(0) |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['product_id', 'related_product_id', 'relation_type'], 'prod_rels_prod_related_type_unique')`

### product_reviews

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| product_id | `foreignId` | constrained('products'), cascadeOnDelete |
| contact_id | `foreignId` | nullable, constrained('contacts'), nullOnDelete |
| reviewer_name | `string` |  |
| rating | `unsignedTinyInteger` | 1-5 |
| title | `string` | nullable |
| review_text | `text` | nullable |
| pros | `json` | nullable |
| cons | `json` | nullable |
| is_verified_purchase | `boolean` | default(false) |
| invoice_id | `foreignId` | nullable, constrained('invoices'), nullOnDelete |
| status | `string(20)` | default('pending') — pending, approved, rejected, flagged |
| approved_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| approved_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['product_id', 'status', 'rating'])`
- `$table->index(['organization_id', 'status'])`

### product_specifications

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| product_id | `foreignId` | constrained('products'), cascadeOnDelete |
| spec_group | `string` | nullable — "Physical", "Technical", "Packaging" |
| spec_name | `string` | "Weight", "Dimensions", "Color" |
| spec_value | `text` | "500g", "10x20x5 cm" |
| unit | `string` | nullable — "kg", "cm", "ml" |
| display_order | `unsignedSmallInteger` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['product_id', 'spec_group'])`

### product_variants

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| product_id | `foreignId` | constrained, cascadeOnDelete |
| sku | `string(50)` |  |
| barcode | `string(50)` | nullable |
| name | `string(200)` |  |
| attributes | `json` | e.g., {"size": "XL", "color": "Red"} |
| purchase_price | `decimal(18, 4)` | nullable — Override parent |
| selling_price | `decimal(18, 4)` | nullable — Override parent |
| image_url | `string(500)` | nullable |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['product_id', 'sku'])`

### inventory_batches

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| product_id | `foreignId` | constrained, cascadeOnDelete |
| product_variant_id | `foreignId` | nullable, constrained, cascadeOnDelete |
| warehouse_id | `foreignId` | constrained, cascadeOnDelete |
| batch_number | `string(50)` |  |
| lot_number | `string(50)` | nullable |
| serial_number | `string(100)` | nullable — For serialized items |
| manufacturing_date | `date` | nullable |
| expiry_date | `date` | nullable |
| received_date | `date` |  |
| quantity | `decimal(15, 4)` |  |
| reserved_quantity | `decimal(15, 4)` | default(0) |
| unit_cost | `decimal(15, 4)` |  |
| status | `string(20)` | default('available') — available, reserved, expired, damaged, quarantine |
| supplier_id | `foreignId` | nullable, constrained('contacts'), nullOnDelete |
| grn_number | `string(50)` | nullable — Goods Receipt Note reference |
| metadata | `json` | nullable — Additional batch attributes |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| batch_class_id | `unsignedBigInteger` | nullable |

Indexes:

- `$table->unique(['organization_id', 'product_id', 'warehouse_id', 'batch_number'], 'inv_batches_org_prod_wh_batch_unique')`
- `$table->index(['organization_id', 'expiry_date'])`
- `$table->index('serial_number')`

Foreign keys:

- `$table->foreign('batch_class_id', 'ib_class_fk')->references('id')->on('batch_classes')`

### batch_characteristic_values

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| inventory_batch_id | `unsignedBigInteger` |  |
| batch_characteristic_id | `unsignedBigInteger` |  |
| text_value | `string(255)` | nullable |
| numeric_value | `decimal(18, 4)` | nullable |
| date_value | `date` | nullable |
| boolean_value | `boolean` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['inventory_batch_id', 'batch_characteristic_id'], 'bcv_batch_char_unq')`

Foreign keys:

- `$table->foreign('organization_id', 'bcv_org_fk')->references('id')->on('organizations')`
- `$table->foreign('inventory_batch_id', 'bcv_batch_fk')->references('id')->on('inventory_batches')`
- `$table->foreign('batch_characteristic_id', 'bcv_char_fk')->references('id')->on('batch_characteristics')`

### batch_where_used_records

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| inventory_batch_id | `unsignedBigInteger` |  |
| usage_type | `enum(['work_order', 'process_order', 'sales_invoice', 'stock_transfer', 'adjustment'])` | default('work_order') |
| reference_id | `unsignedBigInteger` |  |
| reference_number | `string(100)` | nullable |
| product_id | `unsignedBigInteger` | nullable |
| quantity_used | `decimal(18, 4)` |  |
| used_at | `dateTime` |  |
| warehouse_id | `unsignedBigInteger` | nullable |
| recorded_by | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['inventory_batch_id'], 'bwu_batch_idx')`
- `$table->index(['usage_type', 'reference_id'], 'bwu_ref_idx')`
- `$table->index(['organization_id', 'used_at'], 'bwu_org_used_at_idx')`

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')`
- `$table->foreign('inventory_batch_id', 'bwu_batch_fk')->references('id')->on('inventory_batches')`
- `$table->foreign('product_id', 'bwu_product_fk')->references('id')->on('products')`
- `$table->foreign('warehouse_id', 'bwu_warehouse_fk')->references('id')->on('warehouses')`
- `$table->foreign('recorded_by', 'bwu_recorded_by_fk')->references('id')->on('users')`

### goods_issue_lines

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| goods_issue_id | `unsignedBigInteger` |  |
| product_id | `unsignedBigInteger` |  |
| variant_id | `unsignedBigInteger` | nullable |
| warehouse_id | `unsignedBigInteger` |  |
| location_id | `unsignedBigInteger` | nullable |
| batch_id | `unsignedBigInteger` | nullable |
| quantity | `decimal(10, 4)` |  |
| unit_id | `unsignedBigInteger` | nullable |
| unit_cost | `decimal(15, 4)` | default(0) |
| total_value | `decimal(15, 4)` | default(0) |
| serial_number | `string` | nullable |
| notes | `string` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('goods_issue_id')`
- `$table->index('product_id')`

Foreign keys:

- `$table->foreign('goods_issue_id')->references('id')->on('goods_issues')->cascadeOnDelete()`
- `$table->foreign('product_id')->references('id')->on('products')`
- `$table->foreign('variant_id')->references('id')->on('product_variants')->nullOnDelete()`
- `$table->foreign('warehouse_id')->references('id')->on('warehouses')`
- `$table->foreign('location_id')->references('id')->on('warehouse_locations')->nullOnDelete()`
- `$table->foreign('batch_id')->references('id')->on('inventory_batches')->nullOnDelete()`
- `$table->foreign('unit_id')->references('id')->on('units_of_measure')->nullOnDelete()`

### physical_inventory_lines

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| document_id | `foreignId` | constrained('physical_inventory_documents'), cascadeOnDelete |
| product_id | `foreignId` | constrained('products') |
| variant_id | `foreignId` | nullable, constrained('product_variants'), nullOnDelete |
| warehouse_location_id | `foreignId` | nullable, constrained('warehouse_locations'), nullOnDelete |
| book_quantity | `decimal(15, 4)` |  |
| counted_quantity | `decimal(15, 4)` | nullable |
| difference_quantity | `decimal(15, 4)` | nullable |
| unit_cost | `decimal(15, 4)` | nullable |
| difference_value | `decimal(15, 4)` | nullable |
| adjustment_status | `enum(['pending', 'adjusted', 'skipped'])` | default('pending') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['document_id'], 'pil_doc_idx')`
- `$table->index(['product_id', 'adjustment_status'], 'pil_product_adj_idx')`

### picking_list_lines

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| picking_list_id | `foreignId` | constrained('picking_lists'), cascadeOnDelete |
| source_type | `string` |  |
| source_id | `unsignedBigInteger` |  |
| product_id | `foreignId` | constrained('products') |
| variant_id | `foreignId` | nullable, constrained('product_variants'), nullOnDelete |
| from_location_id | `foreignId` | nullable, constrained('warehouse_locations'), nullOnDelete |
| to_location_id | `foreignId` | nullable, constrained('warehouse_locations'), nullOnDelete |
| required_quantity | `decimal(12, 4)` |  |
| picked_quantity | `decimal(12, 4)` | default(0) |
| status | `enum(['pending', 'partial', 'completed', 'skipped'])` | default('pending') |
| picked_at | `timestamp` | nullable |
| picked_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| notes | `string` | nullable |
| sort_order | `integer` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['picking_list_id', 'status'])`
- `$table->index('product_id')`

### price_check_logs

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| station_id | `foreignId` | nullable, constrained('price_check_stations'), nullOnDelete |
| branch_id | `foreignId` | constrained, cascadeOnDelete |
| scan_type | `string(20)` | barcode, qr, rfid, nfc, manual, sku |
| scan_value | `string(255)` | What was scanned |
| scan_successful | `boolean` | default(true) |
| product_id | `foreignId` | nullable, constrained('products'), nullOnDelete |
| variant_id | `foreignId` | nullable, constrained('product_variants'), nullOnDelete |
| product_name | `string` | nullable |
| product_sku | `string` | nullable |
| displayed_price | `decimal(15, 4)` | nullable |
| original_price | `decimal(15, 4)` | nullable — Before promotion |
| currency_code | `string(3)` | nullable |
| has_promotion | `boolean` | default(false) |
| promotion_name | `string` | nullable |
| promotion_discount | `decimal(15, 2)` | nullable |
| contact_id | `foreignId` | nullable, constrained('contacts'), nullOnDelete |
| loyalty_tier | `string` | nullable |
| stock_available | `decimal(15, 4)` | nullable |
| stock_status | `string(20)` | nullable — in_stock, low_stock, out_of_stock |
| error_type | `string(30)` | nullable — not_found, inactive, no_price, scan_error |
| error_message | `text` | nullable |
| scanned_at | `timestamp` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'scanned_at'])`
- `$table->index(['branch_id', 'scanned_at'])`
- `$table->index(['product_id', 'scanned_at'])`
- `$table->index(['scan_value'])`
- `$table->index(['station_id', 'scanned_at'])`
- `$table->index(['error_type'])`

### product_barcodes

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| product_id | `foreignId` | constrained, cascadeOnDelete |
| product_variant_id | `foreignId` | nullable, constrained, cascadeOnDelete |
| barcode_value | `string(100)` |  |
| barcode_type | `string(20)` | default('code128') — ean13, ean8, upc, code128, qr |
| usage | `string(30)` | default('product') — product, unit, pack |
| is_primary | `boolean` | default(false) |
| is_active | `boolean` | default(true) |
| quantity | `decimal(15, 4)` | default(1) |
| unit_id | `foreignId` | nullable, constrained('units_of_measure'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| barcode_image_path | `string` | nullable |
| gtin | `string(14)` | nullable |
| gs1_company_prefix | `string(12)` | nullable |
| variant_id | `unsignedBigInteger` | nullable |
| batch_id | `unsignedBigInteger` | nullable |

Indexes:

- `$table->unique(['organization_id', 'barcode_value'])`
- `$table->index(['organization_id', 'is_active'])`
- `$table->index(['product_id', 'is_primary'])`

### product_images

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| product_id | `foreignId` | constrained('products'), cascadeOnDelete |
| variant_id | `foreignId` | nullable, constrained('product_variants'), nullOnDelete |
| image_path | `string` |  |
| thumbnail_path | `string` | nullable |
| alt_text | `string` | nullable |
| title | `string` | nullable |
| width | `unsignedInteger` | nullable |
| height | `unsignedInteger` | nullable |
| file_size | `unsignedInteger` | nullable |
| image_type | `string(20)` | default('gallery') — gallery, thumbnail, cover, swatch, zoom, lifestyle |
| is_primary | `boolean` | default(false) |
| display_order | `unsignedSmallInteger` | default(0) |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['product_id', 'is_primary'])`
- `$table->index(['variant_id'])`

### product_price_history

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| product_id | `foreignId` | constrained('products'), cascadeOnDelete |
| variant_id | `foreignId` | nullable, constrained('product_variants'), nullOnDelete |
| price_type | `string(30)` | selling_price, purchase_price, mrp, wholesale, special |
| old_price | `decimal(15, 4)` |  |
| new_price | `decimal(15, 4)` |  |
| change_percent | `decimal(8, 2)` |  |
| currency_code | `string(3)` |  |
| reason | `string` | nullable |
| effective_from | `date` |  |
| effective_to | `date` | nullable |
| changed_by | `foreignId` | constrained('users'), cascadeOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['product_id', 'price_type', 'effective_from'])`

### product_videos

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| product_id | `foreignId` | constrained('products'), cascadeOnDelete |
| title | `string` |  |
| video_type | `string(20)` | uploaded, youtube, vimeo, external |
| video_url | `string` | nullable |
| file_path | `string` | nullable |
| thumbnail_path | `string` | nullable |
| duration_seconds | `unsignedInteger` | nullable |
| is_primary | `boolean` | default(false) |
| display_order | `unsignedSmallInteger` | default(0) |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['product_id'])`

### putaway_rules

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| warehouse_id | `foreignId` | constrained('warehouses'), cascadeOnDelete |
| product_id | `foreignId` | nullable, constrained('products'), nullOnDelete |
| product_category_id | `foreignId` | nullable, constrained('categories'), nullOnDelete |
| warehouse_zone | `string` | nullable |
| preferred_location_id | `foreignId` | nullable, constrained('warehouse_locations'), nullOnDelete |
| priority | `tinyInteger` | default(10), comment('Lower number = higher priority') |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'warehouse_id'])`

### safety_data_sheets

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string` | unique |
| organization_id | `unsignedBigInteger` |  |
| product_id | `unsignedBigInteger` |  |
| sds_number | `string(50)` |  |
| version | `string(20)` |  |
| revision_date | `date` |  |
| language_code | `string(5)` | default('en') |
| supplier_name | `string(150)` | nullable |
| emergency_phone | `string(30)` | nullable |
| is_current | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['product_id', 'is_current'], 'sds_product_current_idx')`

Foreign keys:

- `$table->foreign('organization_id', 'sds_org_fk')->references('id')->on('organizations')->onDelete('cascade')`
- `$table->foreign('product_id', 'sds_product_fk')->references('id')->on('products')->onDelete('cascade')`

### safety_data_sheet_sections

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string` | unique |
| safety_data_sheet_id | `unsignedBigInteger` |  |
| section_number | `tinyInteger` |  |
| section_title | `string(100)` |  |
| content | `longText` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('safety_data_sheet_id', 'sdss_sds_fk')->references('id')->on('safety_data_sheets')->onDelete('cascade')`

### serial_numbers

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| serial_number | `string(100)` |  |
| product_id | `foreignId` | constrained('products'), cascadeOnDelete |
| product_variant_id | `foreignId` | nullable, constrained('product_variants'), nullOnDelete |
| batch_id | `foreignId` | nullable, constrained('inventory_batches'), nullOnDelete |
| warehouse_id | `foreignId` | nullable, constrained('warehouses'), nullOnDelete |
| location_id | `foreignId` | nullable, constrained('warehouse_locations'), nullOnDelete |
| status | `enum(['in_stock', 'sold', 'returned', 'scrapped', 'in_transit'])` | default('in_stock') |
| manufacture_date | `date` | nullable |
| expiry_date | `date` | nullable |
| warranty_expiry_date | `date` | nullable |
| received_at | `timestamp` | nullable |
| sold_at | `timestamp` | nullable |
| sold_to_contact_id | `foreignId` | nullable, constrained('contacts'), nullOnDelete |
| current_document_type | `string(50)` | nullable |
| current_document_id | `unsignedBigInteger` | nullable |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'product_id', 'serial_number'])`
- `$table->index(['organization_id', 'status'])`
- `$table->index(['organization_id', 'warehouse_id'])`

### serial_number_movements

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| serial_number_id | `foreignId` | constrained('serial_numbers'), cascadeOnDelete |
| movement_type | `enum(['receipt', 'issue', 'transfer', 'return', 'scrap'])` |  |
| from_warehouse_id | `foreignId` | nullable, constrained('warehouses'), nullOnDelete |
| to_warehouse_id | `foreignId` | nullable, constrained('warehouses'), nullOnDelete |
| document_type | `string(50)` | nullable |
| document_id | `unsignedBigInteger` | nullable |
| moved_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| moved_at | `timestamp` |  |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'serial_number_id'])`
- `$table->index(['organization_id', 'movement_type'])`

### shelf_labels

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| branch_id | `foreignId` | constrained, cascadeOnDelete |
| product_id | `foreignId` | constrained('products'), cascadeOnDelete |
| variant_id | `foreignId` | nullable, constrained('product_variants'), nullOnDelete |
| product_name | `string` |  |
| sku | `string` |  |
| barcode_value | `string` | nullable |
| price | `decimal(15, 4)` |  |
| compare_at_price | `decimal(15, 4)` | nullable — Strikethrough price |
| currency_code | `string(3)` |  |
| unit_label | `string` | nullable — "per kg", "each", "per pack" |
| price_per_unit | `decimal(15, 4)` | nullable — Price per base unit (per 100g, per liter) |
| unit_measure_label | `string` | nullable — "per 100g" |
| aisle | `string` | nullable |
| shelf | `string` | nullable |
| position | `string` | nullable |
| label_type | `string(20)` | default('standard') — standard, promotional, clearance, new_arrival, organic, halal |
| label_size | `string(20)` | default('standard') — small, standard, large, shelf_strip |
| is_digital | `boolean` | default(false) |
| esl_device_id | `string` | nullable |
| last_synced_at | `timestamp` | nullable |
| needs_reprint | `boolean` | default(false) |
| last_printed_at | `timestamp` | nullable |
| print_count | `unsignedInteger` | default(0) |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'branch_id'])`
- `$table->index(['product_id'])`
- `$table->index(['barcode_value'])`
- `$table->index(['needs_reprint'])`

### stock_adjustment_lines

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| stock_adjustment_id | `foreignId` | constrained, cascadeOnDelete |
| product_id | `foreignId` | constrained |
| variant_id | `foreignId` | nullable, constrained('product_variants') |
| location_id | `foreignId` | nullable, constrained('warehouse_locations') |
| system_quantity | `decimal(18, 4)` | Before adjustment |
| actual_quantity | `decimal(18, 4)` | After count |
| difference | `decimal(18, 4)` | Computed difference |
| unit_cost | `decimal(18, 4)` | default(0) |
| total_cost | `decimal(18, 4)` | default(0) |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('stock_adjustment_id', 'sal_adjustment_id_idx')`

### stock_level_snapshots

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| product_id | `foreignId` | constrained('products'), cascadeOnDelete |
| warehouse_id | `foreignId` | nullable, constrained('warehouses'), nullOnDelete |
| quantity_on_hand | `decimal(20, 4)` | default(0) |
| quantity_reserved | `decimal(20, 4)` | default(0) |
| quantity_available | `decimal(20, 4)` | default(0) |
| reorder_point | `decimal(20, 4)` | default(0) |
| is_low_stock | `boolean` | default(false) |
| computed_at | `timestamp` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'product_id', 'warehouse_id'], 'sls_org_product_warehouse_unique')`
- `$table->index(['organization_id', 'is_low_stock'], 'sls_org_low_stock_index')`

### stock_levels

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| product_id | `foreignId` | constrained, cascadeOnDelete |
| variant_id | `foreignId` | nullable, constrained('product_variants'), cascadeOnDelete |
| warehouse_id | `foreignId` | constrained, cascadeOnDelete |
| location_id | `foreignId` | nullable, constrained('warehouse_locations'), nullOnDelete |
| quantity | `decimal(18, 4)` | default(0) |
| reserved_quantity | `decimal(18, 4)` | default(0) — Reserved for orders |
| available_quantity | `decimal(18, 4)` | default(0) |
| average_cost | `decimal(18, 4)` | default(0) |
| last_purchase_price | `decimal(18, 4)` | nullable |
| total_value | `decimal(18, 4)` | default(0) |
| reorder_level | `decimal(18, 4)` | nullable |
| reorder_quantity | `decimal(18, 4)` | nullable |
| maximum_stock | `decimal(18, 4)` | nullable |
| last_count_date | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['product_id', 'variant_id', 'warehouse_id', 'location_id'], 'stock_level_unique')`
- `$table->index(['organization_id', 'warehouse_id'])`
- `$table->index(['product_id', 'warehouse_id'])`

### stock_movements

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| product_id | `foreignId` | constrained, cascadeOnDelete |
| variant_id | `foreignId` | nullable, constrained('product_variants'), nullOnDelete |
| warehouse_id | `foreignId` | constrained, cascadeOnDelete |
| location_id | `foreignId` | nullable, constrained('warehouse_locations'), nullOnDelete |
| movement_type | `enum(['purchase', 'sale', 'transfer_in', 'transfer_out', 'adjustment', 'return_in', 'return_out', 'production_in', 'production_out', 'opening', ])` |  |
| direction | `enum(['in', 'out'])` |  |
| quantity | `decimal(18, 4)` |  |
| unit_cost | `decimal(18, 4)` | default(0) |
| total_cost | `decimal(18, 4)` | default(0) |
| balance_after | `decimal(18, 4)` |  |
| reference_type | `string(50)` | nullable — invoice, bill, transfer, adjustment |
| reference_id | `unsignedBigInteger` | nullable |
| reference_number | `string(50)` | nullable |
| from_warehouse_id | `foreignId` | nullable, constrained('warehouses'), nullOnDelete |
| to_warehouse_id | `foreignId` | nullable, constrained('warehouses'), nullOnDelete |
| notes | `text` | nullable |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'product_id'])`
- `$table->index(['organization_id', 'warehouse_id'])`
- `$table->index(['organization_id', 'created_at'])`
- `$table->index(['reference_type', 'reference_id'])`

Added by later migrations:

- `0500_stock_movement_material_types.php`: `$table->enum('movement_type', [...self::TYPES, 'material_issue', 'material_return'])->change()`
- `0500_stock_movement_material_types.php`: `$table->enum('direction', ['in', 'out'])->change()`

### stock_transfer_lines

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| stock_transfer_id | `foreignId` | constrained, cascadeOnDelete |
| product_id | `foreignId` | constrained |
| variant_id | `foreignId` | nullable, constrained('product_variants') |
| quantity_sent | `decimal(18, 4)` |  |
| quantity_received | `decimal(18, 4)` | default(0) |
| unit_cost | `decimal(18, 4)` | default(0) |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('stock_transfer_id', 'stl_transfer_id_idx')`

### warehouse_transfer_order_items

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| transfer_order_id | `foreignId` | constrained('warehouse_transfer_orders'), cascadeOnDelete |
| product_id | `foreignId` | constrained('products') |
| variant_id | `foreignId` | nullable, constrained('product_variants'), nullOnDelete |
| source_location_id | `foreignId` | nullable, constrained('warehouse_locations'), nullOnDelete |
| dest_location_id | `foreignId` | nullable, constrained('warehouse_locations'), nullOnDelete |
| requested_quantity | `decimal(15, 4)` |  |
| transferred_quantity | `decimal(15, 4)` | default(0) |
| status | `enum(['open', 'partially_transferred', 'transferred', 'cancelled'])` | default('open') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['transfer_order_id'], 'wtoi_to_idx')`

### transfer_prices

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| from_profit_center_id | `unsignedBigInteger` | nullable |
| to_profit_center_id | `unsignedBigInteger` | nullable |
| from_cost_center_id | `unsignedBigInteger` | nullable |
| to_cost_center_id | `unsignedBigInteger` | nullable |
| product_id | `unsignedBigInteger` | nullable |
| cost_element_id | `unsignedBigInteger` | nullable |
| transfer_price_method | `string(30)` | default('standard_cost') |
| base_price | `decimal(18, 4)` |  |
| markup_percentage | `decimal(8, 4)` | default(0) |
| effective_from | `date` |  |
| effective_to | `date` | nullable |
| currency_code | `string(3)` |  |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['from_profit_center_id', 'to_profit_center_id'], 'tp_pc_idx')`
- `$table->index(['product_id', 'is_active'], 'tp_product_active_idx')`
- `$table->index(['effective_from', 'effective_to'], 'tp_effectivity_idx')`

Foreign keys:

- `$table->foreign('organization_id', 'tp_org_fk')->references('id')->on('organizations')->onDelete('cascade')`
- `$table->foreign('from_profit_center_id', 'tp_from_pc_fk')->references('id')->on('profit_centers')->onDelete('set null')`
- `$table->foreign('to_profit_center_id', 'tp_to_pc_fk')->references('id')->on('profit_centers')->onDelete('set null')`
- `$table->foreign('from_cost_center_id', 'tp_from_cc_fk')->references('id')->on('cost_centers')->onDelete('set null')`
- `$table->foreign('to_cost_center_id', 'tp_to_cc_fk')->references('id')->on('cost_centers')->onDelete('set null')`
- `$table->foreign('product_id', 'tp_product_fk')->references('id')->on('products')->onDelete('set null')`
- `$table->foreign('cost_element_id', 'tp_cost_elem_fk')->references('id')->on('cost_elements')->onDelete('set null')`

### transfer_price_conditions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| transfer_price_id | `unsignedBigInteger` |  |
| version_id | `unsignedBigInteger` |  |
| condition_type | `string(30)` |  |
| amount | `decimal(18, 4)` |  |
| is_percentage | `boolean` | default(false) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('transfer_price_id', 'tpc_tp_fk')->references('id')->on('transfer_prices')->onDelete('cascade')`
- `$table->foreign('version_id', 'tpc_version_fk')->references('id')->on('transfer_price_versions')->onDelete('cascade')`

### transfer_price_history

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| transfer_price_id | `unsignedBigInteger` |  |
| changed_by | `unsignedBigInteger` | nullable |
| old_price | `decimal(18, 4)` |  |
| new_price | `decimal(18, 4)` |  |
| change_reason | `text` | nullable |
| changed_at | `dateTime` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('transfer_price_id', 'tph_tp_fk')->references('id')->on('transfer_prices')->onDelete('cascade')`
- `$table->foreign('changed_by', 'tph_user_fk')->references('id')->on('users')->onDelete('set null')`

## 0360_analytics.php

### dim_product

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `unsignedBigInteger` |  |
| product_id | `unsignedBigInteger` | nullable |
| product_code | `string(50)` |  |
| product_name | `string` |  |
| category_name | `string(100)` |  |
| subcategory_name | `string(100)` | nullable |
| unit_of_measure | `string(20)` |  |
| product_type | `string(30)` |  |
| is_active | `boolean` | default(true) |
| synced_at | `dateTime` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('organization_id', 'dim_product_org_id_idx')`

Foreign keys:

- `$table->foreign('product_id', 'dim_product_prod_id_fk')->references('id')->on('products')->onDelete('set null')`

### fact_inventory_movements

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `unsignedBigInteger` |  |
| dim_product_id | `unsignedBigInteger` |  |
| dim_warehouse_id | `unsignedBigInteger` |  |
| dim_time_id | `unsignedBigInteger` |  |
| movement_type | `string(30)` |  |
| quantity_in | `decimal(18, 4)` | default(0) |
| quantity_out | `decimal(18, 4)` | default(0) |
| quantity_balance | `decimal(18, 4)` |  |
| unit_cost | `decimal(18, 4)` | default(0) |
| total_cost | `decimal(18, 4)` | default(0) |
| currency_code | `char(3)` |  |
| reference_type | `string(50)` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'dim_time_id'], 'fact_inv_org_time_idx')`
- `$table->index(['dim_product_id', 'dim_warehouse_id', 'dim_time_id'], 'fact_inv_prod_wh_time_idx')`

Foreign keys:

- `$table->foreign('dim_product_id', 'fact_inv_prod_id_fk')->references('id')->on('dim_product')->onDelete('restrict')`
- `$table->foreign('dim_warehouse_id', 'fact_inv_wh_id_fk')->references('id')->on('dim_warehouse')->onDelete('restrict')`
- `$table->foreign('dim_time_id', 'fact_inv_time_id_fk')->references('id')->on('dim_time')->onDelete('restrict')`

### fact_purchases

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `unsignedBigInteger` |  |
| dim_product_id | `unsignedBigInteger` |  |
| dim_vendor_id | `unsignedBigInteger` |  |
| dim_time_id | `unsignedBigInteger` |  |
| dim_warehouse_id | `unsignedBigInteger` | nullable |
| purchase_order_id | `unsignedBigInteger` | nullable |
| bill_id | `unsignedBigInteger` | nullable |
| quantity | `decimal(18, 4)` |  |
| unit_price | `decimal(18, 4)` |  |
| net_amount | `decimal(18, 4)` |  |
| tax_amount | `decimal(18, 4)` |  |
| gross_amount | `decimal(18, 4)` |  |
| currency_code | `char(3)` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'dim_time_id'], 'fact_purch_org_time_idx')`
- `$table->index(['dim_vendor_id', 'dim_time_id'], 'fact_purch_vendor_time_idx')`
- `$table->index(['dim_product_id', 'dim_time_id'], 'fact_purch_prod_time_idx')`

Foreign keys:

- `$table->foreign('dim_product_id', 'fact_purch_prod_id_fk')->references('id')->on('dim_product')->onDelete('restrict')`
- `$table->foreign('dim_vendor_id', 'fact_purch_vendor_id_fk')->references('id')->on('dim_vendor')->onDelete('restrict')`
- `$table->foreign('dim_time_id', 'fact_purch_time_id_fk')->references('id')->on('dim_time')->onDelete('restrict')`
- `$table->foreign('dim_warehouse_id', 'fact_purch_wh_id_fk')->references('id')->on('dim_warehouse')->onDelete('set null')`

### fact_sales

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `unsignedBigInteger` |  |
| dim_product_id | `unsignedBigInteger` |  |
| dim_customer_id | `unsignedBigInteger` |  |
| dim_time_id | `unsignedBigInteger` |  |
| dim_warehouse_id | `unsignedBigInteger` | nullable |
| invoice_id | `unsignedBigInteger` | nullable |
| invoice_line_id | `unsignedBigInteger` | nullable |
| quantity | `decimal(18, 4)` |  |
| unit_price | `decimal(18, 4)` |  |
| net_amount | `decimal(18, 4)` |  |
| tax_amount | `decimal(18, 4)` |  |
| gross_amount | `decimal(18, 4)` |  |
| discount_amount | `decimal(18, 4)` | default(0) |
| cost_amount | `decimal(18, 4)` | default(0) |
| gross_margin | `decimal(18, 4)` | default(0) |
| currency_code | `char(3)` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'dim_time_id'], 'fact_sales_org_time_idx')`
- `$table->index(['dim_customer_id', 'dim_time_id'], 'fact_sales_cust_time_idx')`
- `$table->index(['dim_product_id', 'dim_time_id'], 'fact_sales_prod_time_idx')`

Foreign keys:

- `$table->foreign('dim_product_id', 'fact_sales_prod_id_fk')->references('id')->on('dim_product')->onDelete('restrict')`
- `$table->foreign('dim_customer_id', 'fact_sales_cust_id_fk')->references('id')->on('dim_customer')->onDelete('restrict')`
- `$table->foreign('dim_time_id', 'fact_sales_time_id_fk')->references('id')->on('dim_time')->onDelete('restrict')`
- `$table->foreign('dim_warehouse_id', 'fact_sales_wh_id_fk')->references('id')->on('dim_warehouse')->onDelete('set null')`

### ecommerce_order_items

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| order_id | `foreignId` | constrained('ecommerce_orders'), cascadeOnDelete |
| external_product_id | `string` | nullable |
| external_variant_id | `string` | nullable |
| sku | `string` | nullable |
| name | `string` |  |
| quantity | `unsignedInteger` |  |
| unit_price | `decimal(15, 2)` |  |
| discount_amount | `decimal(15, 2)` | default(0) |
| tax_amount | `decimal(15, 2)` | default(0) |
| total_amount | `decimal(15, 2)` |  |
| product_id | `foreignId` | nullable, constrained('products'), nullOnDelete |
| fulfilled_quantity | `unsignedInteger` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

### ecommerce_product_mappings

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| channel_id | `foreignId` | constrained('ecommerce_channels'), cascadeOnDelete |
| product_id | `foreignId` | constrained('products'), cascadeOnDelete |
| external_product_id | `string` |  |
| external_variant_id | `string` | nullable |
| external_sku | `string` | nullable |
| sync_enabled | `boolean` | default(true) |
| last_sync_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['channel_id', 'external_product_id', 'external_variant_id'], 'ecom_product_mapping_unique')`
- `$table->index(['channel_id', 'product_id'])`

### equipment_spare_parts

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| equipment_id | `unsignedBigInteger` |  |
| product_id | `foreignId` | constrained('products'), cascadeOnDelete |
| recommended_stock_qty | `decimal(15, 4)` | default(0) |
| current_stock_qty | `decimal(15, 4)` | default(0) |
| is_critical | `boolean` | default(false) |
| lead_time_days | `decimal(8, 2)` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['equipment_id', 'product_id'], 'esp_equip_product_unique')`

### maintenance_order_parts

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| maintenance_order_id | `unsignedBigInteger` |  |
| product_id | `unsignedBigInteger` | nullable |
| description | `string` |  |
| quantity_required | `decimal(10, 4)` | default(1.0000) |
| quantity_used | `decimal(10, 4)` | default(0.0000) |
| unit_cost | `decimal(12, 4)` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('maintenance_order_id')`

Foreign keys:

- `$table->foreign('maintenance_order_id')->references('id')->on('maintenance_orders')->cascadeOnDelete()`
- `$table->foreign('product_id')->references('id')->on('products')->nullOnDelete()`

## 0370_manufacturing_2.php

### approved_vendor_lists

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| supplier_id | `unsignedBigInteger` |  |
| product_id | `unsignedBigInteger` | nullable |
| approved_date | `date` |  |
| expiry_date | `date` | nullable |
| status | `enum(['active', 'suspended', 'expired', 'revoked'])` | default('active') |
| approval_conditions | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete()`
- `$table->foreign('supplier_id', 'avl_supplier_fk')->references('id')->on('contacts')->cascadeOnDelete()`
- `$table->foreign('product_id', 'avl_product_fk')->references('id')->on('products')->nullOnDelete()`

### bom_templates

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| bom_number | `string(50)` |  |
| name | `string(200)` |  |
| description | `text` | nullable |
| product_id | `foreignId` | constrained, cascadeOnDelete |
| variant_id | `foreignId` | nullable, constrained('product_variants'), nullOnDelete |
| output_quantity | `decimal(15, 4)` | default(1) |
| output_unit_id | `foreignId` | nullable, constrained('units_of_measure'), nullOnDelete |
| default_warehouse_id | `foreignId` | nullable, constrained('warehouses'), nullOnDelete |
| estimated_hours | `unsignedSmallInteger` | nullable |
| estimated_labor_cost | `decimal(15, 4)` | nullable |
| overhead_cost | `decimal(15, 4)` | default(0) |
| status | `enum(['draft', 'active', 'inactive'])` | default('draft') |
| effective_from | `date` | nullable |
| effective_to | `date` | nullable |
| version | `unsignedSmallInteger` | default(1) |
| notes | `text` | nullable |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'bom_number'])`
- `$table->index(['organization_id', 'status'])`
- `$table->index(['organization_id', 'product_id'])`

### bom_lines

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| bom_template_id | `foreignId` | constrained, cascadeOnDelete |
| product_id | `foreignId` | constrained, cascadeOnDelete |
| variant_id | `foreignId` | nullable, constrained('product_variants'), nullOnDelete |
| description | `string(500)` | nullable |
| quantity | `decimal(15, 4)` |  |
| unit_id | `foreignId` | nullable, constrained('units_of_measure'), nullOnDelete |
| unit_cost | `decimal(15, 4)` | nullable |
| wastage_percentage | `decimal(5, 2)` | default(0) |
| is_critical | `boolean` | default(false) — Production stops if unavailable |
| warehouse_id | `foreignId` | nullable, constrained, nullOnDelete |
| line_order | `unsignedSmallInteger` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

### bom_operations

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| bom_template_id | `foreignId` | constrained, cascadeOnDelete |
| name | `string(100)` |  |
| description | `text` | nullable |
| instructions | `text` | nullable |
| sequence | `unsignedSmallInteger` | default(0) |
| estimated_minutes | `unsignedSmallInteger` | default(0) |
| labor_cost_per_hour | `decimal(15, 4)` | nullable |
| workstation | `string(100)` | nullable |
| required_skills | `json` | nullable |
| is_subcontracted | `boolean` | default(false) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

### certificates_of_analysis

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string(36)` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| certificate_number | `string(30)` |  |
| product_id | `foreignId` | constrained('products'), cascadeOnDelete |
| batch_number | `string(100)` | nullable |
| inspection_lot_id | `unsignedBigInteger` | nullable |
| contact_id | `foreignId` | nullable, constrained('contacts'), nullOnDelete |
| issue_date | `date` |  |
| test_date | `date` | nullable |
| test_results | `json` |  |
| overall_result | `enum(['pass', 'fail', 'conditional'])` | default('pass') |
| remarks | `text` | nullable |
| issued_by | `foreignId` | constrained('users') |
| approved_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| status | `enum(['draft', 'approved', 'issued', 'revoked'])` | default('draft') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'certificate_number'], 'coa_org_number_unique')`
- `$table->index(['organization_id', 'product_id', 'status'], 'coa_org_product_status_idx')`

### demand_forecasts

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| product_id | `foreignId` | constrained('products') |
| warehouse_id | `foreignId` | nullable, constrained('warehouses'), nullOnDelete |
| forecast_date | `date` |  |
| forecast_quantity | `decimal(12, 4)` |  |
| actual_quantity | `decimal(12, 4)` | nullable |
| notes | `string` | nullable |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'product_id', 'forecast_date'])`
- `$table->index(['organization_id', 'product_id'])`

### kanban_control_cycles

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string(36)` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| product_id | `foreignId` | constrained('products'), cascadeOnDelete |
| supply_area_id | `foreignId` | constrained('kanban_supply_areas'), cascadeOnDelete |
| replenishment_strategy | `enum(['production', 'purchase', 'stock_transfer'])` | default('production') |
| number_of_cards | `integer` | default(1) |
| replenishment_quantity | `decimal(15, 4)` |  |
| safety_stock_quantity | `decimal(15, 4)` | default(0) |
| replenishment_lead_time_days | `integer` | default(1) |
| source_vendor_id | `foreignId` | nullable, constrained('contacts'), nullOnDelete |
| source_warehouse_id | `foreignId` | nullable, constrained('warehouses'), nullOnDelete |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'product_id'], 'kcc_org_product_idx')`

### kanban_cards

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string(36)` | unique |
| control_cycle_id | `foreignId` | constrained('kanban_control_cycles'), cascadeOnDelete |
| card_number | `string(30)` |  |
| status | `enum(['full', 'empty', 'in_replenishment', 'waiting'])` | default('full') |
| current_quantity | `decimal(15, 4)` | default(0) |
| emptied_at | `timestamp` | nullable |
| replenishment_triggered_at | `timestamp` | nullable |
| filled_at | `timestamp` | nullable |
| triggered_document_id | `unsignedBigInteger` | nullable |
| triggered_document_type | `string(30)` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['control_cycle_id', 'card_number'], 'kc_cycle_number_unique')`
- `$table->index(['control_cycle_id', 'status'], 'kc_cycle_status_idx')`

### mrp_demand_items

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| mrp_run_id | `foreignId` | constrained('mrp_runs'), cascadeOnDelete |
| product_id | `foreignId` | constrained('products') |
| source_type | `enum(['sales_order', 'forecast', 'safety_stock', 'bom', 'pir'])` | default('sales_order') |
| source_id | `unsignedBigInteger` | nullable |
| required_date | `date` |  |
| required_quantity | `decimal(12, 4)` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('mrp_run_id')`
- `$table->index('product_id')`

### mrp_planned_orders

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| mrp_run_id | `foreignId` | constrained('mrp_runs'), cascadeOnDelete |
| product_id | `foreignId` | constrained('products') |
| order_type | `enum(['purchase', 'production', 'transfer'])` | default('purchase') |
| planned_quantity | `decimal(12, 4)` |  |
| planned_start_date | `date` |  |
| planned_end_date | `date` |  |
| status | `enum(['planned', 'firmed', 'converted', 'cancelled'])` | default('planned') |
| source_demand_id | `unsignedBigInteger` | nullable |
| notes | `string` | nullable |
| converted_at | `timestamp` | nullable |
| converted_to_type | `string` | nullable |
| converted_to_id | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| purchase_requisition_id | `unsignedBigInteger` | nullable |

Indexes:

- `$table->index(['organization_id', 'status'])`
- `$table->index(['product_id', 'status'])`

Foreign keys:

- `$table->foreign('purchase_requisition_id')->references('id')->on('purchase_requisitions')->onDelete('set null')`

### planned_independent_requirements

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| product_id | `foreignId` | constrained('products'), cascadeOnDelete |
| version | `unsignedTinyInteger` | default(1) |
| is_active | `boolean` | default(true) |
| quantity | `decimal(12, 4)` |  |
| requirement_date | `date` |  |
| valid_from | `date` | nullable |
| valid_to | `date` | nullable |
| consumed_quantity | `decimal(12, 4)` | default(0) |
| notes | `text` | nullable |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| deleted_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'product_id', 'is_active'], 'pir_org_prod_active_idx')`
- `$table->index(['organization_id', 'requirement_date'], 'pir_org_req_date_idx')`
- `$table->index(['organization_id', 'version', 'is_active'], 'pir_org_ver_active_idx')`

### product_costs

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| product_id | `foreignId` | constrained('products'), cascadeOnDelete |
| cost_version_id | `foreignId` | nullable, constrained('costing_versions'), nullOnDelete |
| cost_type | `string(30)` | default('standard'), comment('standard, actual, planned') |
| material_cost | `decimal(18, 4)` | default(0) |
| labour_cost | `decimal(18, 4)` | default(0) |
| overhead_cost | `decimal(18, 4)` | default(0) |
| subcontracting_cost | `decimal(18, 4)` | default(0) |
| total_cost | `decimal(18, 4)` | default(0) |
| currency_code | `string(3)` | default('SAR') |
| effective_from | `date` | nullable |
| effective_to | `date` | nullable |
| costed_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'product_id'])`
- `$table->index(['product_id', 'cost_type', 'effective_from'])`

### product_standard_costs

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| costing_version_id | `unsignedBigInteger` |  |
| product_id | `unsignedBigInteger` |  |
| variant_id | `unsignedBigInteger` | nullable |
| material_cost | `decimal(15, 4)` | default(0) |
| labor_cost | `decimal(15, 4)` | default(0) |
| overhead_cost | `decimal(15, 4)` | default(0) |
| subcontracting_cost | `decimal(15, 4)` | default(0) |
| total_standard_cost | `decimal(15, 4)` | default(0) |
| cost_per_unit | `decimal(15, 4)` | default(0) |
| calculated_at | `timestamp` | nullable |
| bom_id | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['costing_version_id', 'product_id', 'variant_id'], 'psc_version_product_variant_unique')`
- `$table->index(['costing_version_id', 'product_id'], 'psc_version_product_idx')`

Foreign keys:

- `$table->foreign('costing_version_id')->references('id')->on('costing_versions')->onDelete('cascade')`
- `$table->foreign('product_id')->references('id')->on('products')->onDelete('cascade')`
- `$table->foreign('variant_id')->references('id')->on('product_variants')->onDelete('set null')`
- `$table->foreign('bom_id')->references('id')->on('bom_templates')->onDelete('set null')`

### cost_components

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| standard_cost_id | `unsignedBigInteger` |  |
| component_type | `enum(['material', 'labor', 'overhead', 'subcontracting'])` |  |
| reference_type | `string(50)` | nullable |
| reference_id | `unsignedBigInteger` | nullable |
| description | `string(200)` |  |
| quantity | `decimal(15, 4)` | default(0) |
| unit_cost | `decimal(15, 4)` | default(0) |
| total_cost | `decimal(15, 4)` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['standard_cost_id', 'component_type'], 'cc_sc_type_idx')`

Foreign keys:

- `$table->foreign('standard_cost_id')->references('id')->on('product_standard_costs')->onDelete('cascade')`

### quality_cost_entries

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| cost_category | `enum(['prevention', 'appraisal', 'internal_failure', 'external_failure'])` | default('internal_failure') |
| cost_subcategory | `string(100)` | nullable |
| reference_type | `string(50)` | nullable |
| reference_id | `unsignedBigInteger` | nullable |
| product_id | `unsignedBigInteger` | nullable |
| period | `unsignedTinyInteger` |  |
| fiscal_year | `unsignedSmallInteger` |  |
| amount | `decimal(18, 4)` |  |
| description | `text` | nullable |
| recorded_by | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'period', 'fiscal_year'], 'qce_org_period_fy_idx')`
- `$table->index(['organization_id', 'cost_category'], 'qce_org_category_idx')`

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')`
- `$table->foreign('product_id', 'qce_product_fk')->references('id')->on('products')`
- `$table->foreign('recorded_by', 'qce_recorded_by_fk')->references('id')->on('users')`

### quality_notifications

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| notification_number | `string` |  |
| notification_type | `enum(['defect', 'complaint', 'improvement', 'deviation', ])` | default('defect') |
| source_type | `enum(['inspection_lot', 'customer', 'supplier', 'internal', ])` | default('internal') |
| source_id | `unsignedBigInteger` | nullable |
| product_id | `foreignId` | nullable, constrained('products'), nullOnDelete |
| title | `string` |  |
| description | `text` |  |
| priority | `enum(['low', 'medium', 'high', 'critical'])` | default('medium') |
| status | `enum(['open', 'in_progress', 'resolved', 'closed', ])` | default('open') |
| assigned_to | `foreignId` | nullable, constrained('users'), nullOnDelete |
| root_cause | `text` | nullable |
| corrective_action | `text` | nullable |
| preventive_action | `text` | nullable |
| due_date | `date` | nullable |
| resolved_at | `timestamp` | nullable |
| resolved_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| deleted_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'notification_number'])`
- `$table->index(['organization_id', 'status', 'priority'])`

### defect_records

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| quality_notification_id | `foreignId` | constrained('quality_notifications'), cascadeOnDelete |
| defect_type | `string` |  |
| defect_code | `string` | nullable |
| quantity | `integer` | default(1) |
| severity | `enum(['minor', 'major', 'critical'])` | default('minor') |
| description | `text` | nullable |
| location | `string` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('quality_notification_id')`

### quality_plans

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| name | `string` |  |
| product_id | `foreignId` | nullable, constrained('products'), nullOnDelete |
| product_category_id | `foreignId` | nullable, constrained('categories'), nullOnDelete |
| inspection_stage | `enum(['goods_receipt', 'production', 'pre_shipment', 'in_process', 'final', ])` | default('goods_receipt') |
| is_active | `boolean` | default(true) |
| description | `text` | nullable |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| deleted_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'is_active'])`
- `$table->index(['organization_id', 'product_id'])`

### inspection_lot_configs

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| product_id | `foreignId` | constrained('products'), cascadeOnDelete |
| inspection_trigger | `enum(['goods_receipt', 'goods_issue', 'production_completion', 'manual'])` | default('goods_receipt') |
| auto_create | `boolean` | default(true) |
| sample_percentage | `decimal(5, 2)` | default(100) |
| quality_plan_id | `foreignId` | nullable, constrained('quality_plans'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'product_id', 'inspection_trigger'], 'ilc_org_prod_trigger_unique')`

### inspection_lots

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| lot_number | `string` |  |
| quality_plan_id | `foreignId` | nullable, constrained('quality_plans'), nullOnDelete |
| product_id | `foreignId` | constrained('products'), cascadeOnDelete |
| warehouse_id | `foreignId` | nullable, constrained('warehouses'), nullOnDelete |
| source_type | `enum(['purchase_order', 'production', 'transfer', 'manual', ])` | default('manual') |
| source_id | `unsignedBigInteger` | nullable |
| quantity | `decimal(12, 4)` |  |
| inspected_quantity | `decimal(12, 4)` | default(0) |
| accepted_quantity | `decimal(12, 4)` | default(0) |
| rejected_quantity | `decimal(12, 4)` | default(0) |
| status | `enum(['pending', 'in_inspection', 'accepted', 'rejected', 'partial_accept', ])` | default('pending') |
| inspection_date | `date` | nullable |
| inspected_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| notes | `text` | nullable |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'lot_number'])`
- `$table->index(['organization_id', 'status'])`
- `$table->index(['product_id', 'status'])`

### procurement_inspection_configs

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| product_id | `foreignId` | nullable, constrained('products'), nullOnDelete |
| vendor_id | `foreignId` | nullable, constrained('contacts'), nullOnDelete |
| inspection_required | `boolean` | default(false) |
| sampling_percentage | `decimal(5, 2)` | default(100) |
| auto_approve_below_defect_rate | `decimal(5, 2)` | nullable |
| quality_plan_id | `foreignId` | nullable, constrained('quality_plans'), nullOnDelete |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'product_id', 'vendor_id'], 'proc_insp_cfg_org_prod_vnd')`

### procurement_inspections

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| purchase_order_id | `foreignId` | nullable, constrained('purchase_orders'), nullOnDelete |
| goods_receipt_id | `unsignedBigInteger` | nullable |
| product_id | `foreignId` | constrained('products') |
| vendor_id | `foreignId` | nullable, constrained('contacts'), nullOnDelete |
| inspection_lot_id | `foreignId` | nullable, constrained('inspection_lots'), nullOnDelete |
| quantity_received | `decimal(18, 4)` |  |
| quantity_to_inspect | `decimal(18, 4)` |  |
| quantity_inspected | `decimal(18, 4)` | default(0) |
| quantity_accepted | `decimal(18, 4)` | default(0) |
| quantity_rejected | `decimal(18, 4)` | default(0) |
| status | `string(20)` | default('pending'), comment('pending/in_progress/completed/approved/rejected') |
| defect_rate | `decimal(5, 2)` | nullable |
| inspection_date | `dateTime` | nullable |
| inspected_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['purchase_order_id', 'status'], 'proc_insp_po_status_idx')`
- `$table->index(['product_id', 'vendor_id'], 'proc_insp_prod_vnd_idx')`
- `$table->index(['status', 'inspection_date'], 'proc_insp_status_date_idx')`

### procurement_inspection_results

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| procurement_inspection_id | `foreignId` | constrained('procurement_inspections'), cascadeOnDelete |
| characteristic_name | `string(100)` |  |
| specification_min | `string(50)` | nullable |
| specification_max | `string(50)` | nullable |
| actual_value | `string(100)` | nullable |
| is_within_spec | `boolean` | nullable |
| defect_description | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('procurement_inspection_id', 'proc_insp_res_insp_idx')`

### q_info_records

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| vendor_id | `unsignedBigInteger` | nullable |
| product_id | `unsignedBigInteger` |  |
| inspection_type | `enum(['goods_receipt', 'in_process', 'final', 'delivery', 'returns'])` | default('goods_receipt') |
| skip_lot_plan_id | `unsignedBigInteger` | nullable |
| quality_plan_id | `unsignedBigInteger` | nullable |
| is_active | `boolean` | default(true) |
| release_required | `boolean` | default(false) |
| cert_required | `boolean` | default(false) |
| cert_type | `string(50)` | nullable |
| inspection_interval_days | `unsignedSmallInteger` | nullable |
| last_inspection_date | `date` | nullable |
| next_inspection_date | `date` | nullable |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'vendor_id', 'product_id', 'inspection_type'], 'qir_org_vendor_prod_type_unq')`
- `$table->index(['organization_id', 'product_id'], 'qir_org_product_idx')`

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')`
- `$table->foreign('vendor_id', 'qir_vendor_fk')->references('id')->on('contacts')`
- `$table->foreign('product_id', 'qir_product_fk')->references('id')->on('products')`
- `$table->foreign('skip_lot_plan_id', 'qir_slsp_fk')->references('id')->on('skip_lot_sampling_plans')`
- `$table->foreign('quality_plan_id', 'qir_qp_fk')->references('id')->on('quality_plans')`

### quality_plan_characteristics

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| quality_plan_id | `foreignId` | constrained('quality_plans'), cascadeOnDelete |
| name | `string` |  |
| description | `text` | nullable |
| inspection_method | `string` | nullable |
| measurement_unit | `string` | nullable |
| lower_limit | `decimal(12, 4)` | nullable |
| upper_limit | `decimal(12, 4)` | nullable |
| target_value | `decimal(12, 4)` | nullable |
| is_mandatory | `boolean` | default(true) |
| sort_order | `smallInteger` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('quality_plan_id')`

### inspection_results

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| inspection_lot_id | `foreignId` | constrained('inspection_lots'), cascadeOnDelete |
| quality_plan_characteristic_id | `foreignId` | nullable, constrained('quality_plan_characteristics'), nullOnDelete |
| characteristic_name | `string` |  |
| measured_value | `decimal(12, 4)` | nullable |
| text_result | `string` | nullable |
| is_conforming | `boolean` | nullable |
| notes | `text` | nullable |
| recorded_by | `foreignId` | constrained('users') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('inspection_lot_id')`

### recipes

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| product_id | `foreignId` | constrained, cascadeOnDelete |
| recipe_code | `string(30)` |  |
| name | `string` |  |
| base_quantity | `decimal(18, 4)` |  |
| base_unit_id | `foreignId` | nullable, constrained('units_of_measure'), nullOnDelete |
| recipe_type | `string(20)` | default('master') |
| validity_from | `date` |  |
| validity_to | `date` | nullable |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'recipe_code'], 'recipes_org_code_unique')`
- `$table->index(['product_id', 'is_active'], 'recipes_product_active_idx')`

### recipe_phases

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| recipe_id | `foreignId` | constrained('recipes'), cascadeOnDelete |
| phase_number | `unsignedInteger` |  |
| name | `string` |  |
| operation_description | `text` | nullable |
| resource_type | `string(20)` |  |
| resource_id | `unsignedBigInteger` | nullable |
| duration_hours | `decimal(8, 2)` |  |
| temperature | `decimal(6, 2)` | nullable |
| pressure | `decimal(6, 2)` | nullable |
| agitation_rpm | `unsignedInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

### recipe_resources

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| recipe_id | `foreignId` | constrained('recipes'), cascadeOnDelete |
| recipe_phase_id | `foreignId` | nullable, constrained('recipe_phases'), nullOnDelete |
| material_id | `foreignId` | constrained('products'), cascadeOnDelete |
| quantity | `decimal(18, 4)` |  |
| unit_id | `foreignId` | nullable, constrained('units_of_measure'), nullOnDelete |
| is_co_product | `boolean` | default(false) |
| is_by_product | `boolean` | default(false) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

### returns_inspection_lots

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| rma_request_id | `unsignedBigInteger` | nullable |
| sales_return_id | `unsignedBigInteger` | nullable |
| purchase_return_id | `unsignedBigInteger` | nullable |
| product_id | `unsignedBigInteger` |  |
| warehouse_id | `unsignedBigInteger` | nullable |
| lot_number | `string(50)` |  |
| return_type | `enum(['customer_return', 'vendor_return', 'internal_return'])` | default('customer_return') |
| status | `enum(['open', 'in_inspection', 'usage_decision_made', 'closed', 'cancelled'])` | default('open') |
| received_quantity | `decimal(18, 4)` |  |
| inspected_quantity | `decimal(18, 4)` | default(0) |
| accepted_quantity | `decimal(18, 4)` | default(0) |
| rejected_quantity | `decimal(18, 4)` | default(0) |
| rework_quantity | `decimal(18, 4)` | default(0) |
| usage_decision | `enum(['accept', 'reject', 'rework', 'partial_accept'])` | nullable |
| usage_decision_by | `unsignedBigInteger` | nullable |
| usage_decision_at | `dateTime` | nullable |
| usage_decision_notes | `text` | nullable |
| inspection_start_date | `date` | nullable |
| inspection_end_date | `date` | nullable |
| quality_plan_id | `unsignedBigInteger` | nullable |
| stock_posted | `boolean` | default(false) |
| stock_posted_at | `dateTime` | nullable |
| created_by | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'lot_number'], 'ril_org_lot_unq')`
- `$table->index(['organization_id', 'status'], 'ril_org_status_idx')`
- `$table->index(['product_id'], 'ril_product_idx')`

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete()`
- `$table->foreign('rma_request_id', 'ril_rma_fk')->references('id')->on('rma_requests')->nullOnDelete()`
- `$table->foreign('sales_return_id', 'ril_sales_return_fk')->references('id')->on('sales_returns')->nullOnDelete()`
- `$table->foreign('purchase_return_id', 'ril_purchase_return_fk')->references('id')->on('purchase_returns')->nullOnDelete()`
- `$table->foreign('product_id', 'ril_product_fk')->references('id')->on('products')`
- `$table->foreign('warehouse_id', 'ril_warehouse_fk')->references('id')->on('warehouses')->nullOnDelete()`
- `$table->foreign('usage_decision_by', 'ril_ud_by_fk')->references('id')->on('users')->nullOnDelete()`
- `$table->foreign('quality_plan_id', 'ril_qp_fk')->references('id')->on('quality_plans')->nullOnDelete()`
- `$table->foreign('created_by', 'ril_created_by_fk')->references('id')->on('users')->nullOnDelete()`

### returns_inspection_defects

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| returns_inspection_lot_id | `unsignedBigInteger` |  |
| defect_code | `string(50)` |  |
| defect_description | `text` | nullable |
| severity | `enum(['critical', 'major', 'minor', 'cosmetic'])` | default('minor') |
| quantity_affected | `decimal(18, 4)` | default(0) |
| recommended_action | `enum(['scrap', 'return_to_vendor', 'rework', 'repack', 'accept'])` | nullable |
| actual_action_taken | `enum(['scrapped', 'returned_to_vendor', 'reworked', 'repacked', 'accepted'])` | nullable |
| notes | `text` | nullable |
| recorded_by | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['returns_inspection_lot_id'], 'rid_lot_idx')`

Foreign keys:

- `$table->foreign('organization_id', 'rid_org_fk')->references('id')->on('organizations')->cascadeOnDelete()`
- `$table->foreign('returns_inspection_lot_id', 'rid_lot_fk')->references('id')->on('returns_inspection_lots')->cascadeOnDelete()`
- `$table->foreign('recorded_by', 'rid_recorded_by_fk')->references('id')->on('users')->nullOnDelete()`

### routing_headers

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string(36)` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| product_id | `foreignId` | constrained('products'), cascadeOnDelete |
| routing_number | `string(30)` |  |
| alternative | `string(5)` | default('1') |
| is_default | `boolean` | default(true) |
| valid_from | `date` | nullable |
| valid_to | `date` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'product_id', 'routing_number', 'alternative'], 'rh_org_prod_num_alt_unique')`

### production_versions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| product_id | `foreignId` | constrained, cascadeOnDelete |
| version_code | `string(20)` |  |
| description | `string` | nullable |
| bom_id | `foreignId` | nullable, constrained('bom_templates'), nullOnDelete |
| routing_id | `foreignId` | nullable, constrained('routing_headers'), nullOnDelete |
| lot_size_from | `decimal(18, 4)` | default(0) |
| lot_size_to | `decimal(18, 4)` | nullable |
| valid_from | `date` |  |
| valid_to | `date` | nullable |
| production_plant | `string(50)` | nullable |
| is_default | `boolean` | default(false) |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'product_id', 'version_code'], 'pv_org_product_code_unique')`
- `$table->index(['product_id', 'is_active'], 'pv_product_active_idx')`
- `$table->index(['product_id', 'is_default'], 'pv_product_default_idx')`

### long_term_planned_orders

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| planning_simulation_id | `foreignId` | constrained('planning_simulations'), cascadeOnDelete |
| product_id | `foreignId` | constrained, cascadeOnDelete |
| planned_order_type | `string(20)` |  |
| quantity | `decimal(18, 4)` |  |
| unit_id | `foreignId` | nullable, constrained('units_of_measure'), nullOnDelete |
| planned_start | `date` |  |
| planned_finish | `date` |  |
| production_version_id | `foreignId` | nullable, constrained('production_versions'), nullOnDelete |
| vendor_id | `foreignId` | nullable, constrained('contacts'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['product_id', 'planned_start'], 'ltp_po_product_start_idx')`

### process_orders

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| recipe_id | `foreignId` | constrained('recipes'), cascadeOnDelete |
| product_id | `foreignId` | constrained, cascadeOnDelete |
| order_number | `string(30)` |  |
| planned_quantity | `decimal(18, 4)` |  |
| actual_quantity | `decimal(18, 4)` | nullable |
| unit_id | `foreignId` | nullable, constrained('units_of_measure'), nullOnDelete |
| batch_number | `string(50)` | nullable |
| planned_start | `dateTime` |  |
| planned_finish | `dateTime` |  |
| actual_start | `dateTime` | nullable |
| actual_finish | `dateTime` | nullable |
| status | `string(20)` | default('created') |
| production_version_id | `foreignId` | nullable, constrained('production_versions'), nullOnDelete |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'order_number'], 'proc_ord_org_number_unique')`
- `$table->index(['product_id', 'status'], 'proc_ord_product_status_idx')`
- `$table->index(['status', 'planned_start'], 'proc_ord_status_start_idx')`

### process_order_phases

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| process_order_id | `foreignId` | constrained('process_orders'), cascadeOnDelete |
| recipe_phase_id | `foreignId` | nullable, constrained('recipe_phases'), nullOnDelete |
| phase_number | `unsignedInteger` |  |
| name | `string` |  |
| status | `string(20)` | default('pending') |
| started_at | `dateTime` | nullable |
| completed_at | `dateTime` | nullable |
| actual_temperature | `decimal(6, 2)` | nullable |
| actual_pressure | `decimal(6, 2)` | nullable |
| actual_duration_minutes | `unsignedInteger` | nullable |
| operator_notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

### process_order_resources

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| process_order_id | `foreignId` | constrained('process_orders'), cascadeOnDelete |
| recipe_resource_id | `foreignId` | nullable, constrained('recipe_resources'), nullOnDelete |
| material_id | `foreignId` | constrained('products'), cascadeOnDelete |
| planned_quantity | `decimal(18, 4)` |  |
| actual_quantity | `decimal(18, 4)` | nullable |
| unit_id | `foreignId` | nullable, constrained('units_of_measure'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

### repetitive_mfg_schedules

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| product_id | `foreignId` | constrained, cascadeOnDelete |
| production_version_id | `foreignId` | nullable, constrained('production_versions'), nullOnDelete |
| production_line_id | `foreignId` | constrained('production_lines'), cascadeOnDelete |
| schedule_date_from | `date` |  |
| schedule_date_to | `date` |  |
| total_planned_quantity | `decimal(18, 4)` |  |
| total_confirmed_quantity | `decimal(18, 4)` | default(0) |
| status | `string(20)` | default('planned') |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['product_id', 'schedule_date_from'], 'rms_product_date_idx')`
- `$table->index(['production_line_id', 'status'], 'rms_line_status_idx')`
- `$table->index(['status', 'schedule_date_from'], 'rms_status_date_idx')`

### repetitive_mfg_backflushes

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| repetitive_mfg_schedule_id | `foreignId` | constrained('repetitive_mfg_schedules'), cascadeOnDelete |
| backflush_date | `dateTime` |  |
| quantity_produced | `decimal(18, 4)` |  |
| quantity_scrapped | `decimal(18, 4)` | default(0) |
| component_movements | `json` | nullable |
| labor_time_minutes | `decimal(10, 2)` | nullable |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

### repetitive_mfg_schedule_lines

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| repetitive_mfg_schedule_id | `foreignId` | constrained('repetitive_mfg_schedules'), cascadeOnDelete |
| schedule_date | `date` |  |
| planned_quantity | `decimal(18, 4)` |  |
| confirmed_quantity | `decimal(18, 4)` | default(0) |
| status | `string(20)` | default('planned') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

## 0380_manufacturing_3.php

### routing_operations

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| routing_id | `foreignId` | constrained('routing_headers'), cascadeOnDelete |
| sequence_number | `integer` | 10, 20, 30... |
| operation_code | `string(20)` |  |
| description | `string(255)` |  |
| work_center_id | `foreignId` | constrained('work_centers'), cascadeOnDelete |
| setup_time | `decimal(10, 4)` | default(0) — hours |
| machine_time | `decimal(10, 4)` | default(0) — hours per unit |
| labor_time | `decimal(10, 4)` | default(0) — hours per unit |
| control_key | `string(10)` | nullable — PP01=internal, PP02=external |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| inter_operation_time | `decimal(8, 2)` | default(0), comment('Buffer/transit hours between operations') |

Indexes:

- `$table->index(['routing_id', 'sequence_number'], 'ro_routing_seq_idx')`

### skip_lot_decisions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| skip_lot_sampling_plan_id | `unsignedBigInteger` |  |
| vendor_id | `unsignedBigInteger` | nullable |
| product_id | `unsignedBigInteger` | nullable |
| current_level | `enum(['skip_lot', 'reduced', 'normal', 'tightened', 'rejected'])` | default('normal') |
| lots_inspected_at_level | `unsignedInteger` | default(0) |
| consecutive_accepted | `unsignedInteger` | default(0) |
| consecutive_rejected | `unsignedInteger` | default(0) |
| last_inspection_lot_id | `unsignedBigInteger` | nullable |
| last_evaluated_at | `dateTime` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'vendor_id', 'product_id'], 'sld_org_vendor_product_idx')`

Foreign keys:

- `$table->foreign('organization_id', 'sld_org_fk')->references('id')->on('organizations')`
- `$table->foreign('skip_lot_sampling_plan_id', 'sld_plan_fk')->references('id')->on('skip_lot_sampling_plans')`
- `$table->foreign('vendor_id', 'sld_vendor_fk')->references('id')->on('contacts')`
- `$table->foreign('product_id', 'sld_product_fk')->references('id')->on('products')`
- `$table->foreign('last_inspection_lot_id', 'sld_last_lot_fk')->references('id')->on('inspection_lots')`

### stability_studies

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| study_number | `string(50)` |  |
| product_id | `unsignedBigInteger` |  |
| inventory_batch_id | `unsignedBigInteger` | nullable |
| study_type | `enum(['real_time', 'accelerated', 'intermediate'])` | default('real_time') |
| status | `enum(['planned', 'active', 'completed', 'discontinued'])` | default('planned') |
| start_date | `date` |  |
| planned_end_date | `date` | nullable |
| storage_condition | `string(100)` | nullable |
| protocol_reference | `string(100)` | nullable |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'study_number'], 'ss_org_number_unq')`

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')`
- `$table->foreign('product_id', 'ss_product_fk')->references('id')->on('products')`
- `$table->foreign('inventory_batch_id', 'ss_batch_fk')->references('id')->on('inventory_batches')`

### stability_study_time_points

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| stability_study_id | `unsignedBigInteger` |  |
| time_point | `string(20)` |  |
| scheduled_date | `date` |  |
| actual_date | `date` | nullable |
| status | `enum(['scheduled', 'in_progress', 'completed', 'missed'])` | default('scheduled') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['stability_study_id'], 'sstp_study_idx')`

Foreign keys:

- `$table->foreign('organization_id', 'sstp_org_fk')->references('id')->on('organizations')`
- `$table->foreign('stability_study_id', 'sstp_study_fk')->references('id')->on('stability_studies')`

### stability_study_results

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| stability_study_time_point_id | `unsignedBigInteger` |  |
| parameter_name | `string(100)` |  |
| specification_min | `decimal(18, 4)` | nullable |
| specification_max | `decimal(18, 4)` | nullable |
| result_value | `decimal(18, 4)` | nullable |
| result_text | `string(255)` | nullable |
| unit_of_measure | `string(20)` | nullable |
| is_pass | `boolean` | nullable |
| tested_by | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['stability_study_time_point_id'], 'ssr_timepoint_idx')`

Foreign keys:

- `$table->foreign('organization_id', 'ssr_org_fk')->references('id')->on('organizations')`
- `$table->foreign('stability_study_time_point_id', 'ssr_timepoint_fk')->references('id')->on('stability_study_time_points')`
- `$table->foreign('tested_by', 'ssr_tested_by_fk')->references('id')->on('users')`

### subcontract_components

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| order_id | `unsignedBigInteger` |  |
| product_id | `unsignedBigInteger` |  |
| variant_id | `unsignedBigInteger` | nullable |
| required_quantity | `decimal(15, 4)` |  |
| transferred_quantity | `decimal(15, 4)` | default(0) |
| unit_id | `unsignedBigInteger` |  |
| warehouse_id | `unsignedBigInteger` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('order_id', 'sccomp_order_idx')`

Foreign keys:

- `$table->foreign('order_id')->references('id')->on('subcontract_orders')->onDelete('cascade')`
- `$table->foreign('product_id')->references('id')->on('products')->onDelete('restrict')`
- `$table->foreign('variant_id')->references('id')->on('product_variants')->onDelete('set null')`
- `$table->foreign('unit_id')->references('id')->on('units_of_measure')`
- `$table->foreign('warehouse_id')->references('id')->on('warehouses')`

### subcontract_order_lines

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| order_id | `unsignedBigInteger` |  |
| product_id | `unsignedBigInteger` |  |
| variant_id | `unsignedBigInteger` | nullable |
| ordered_quantity | `decimal(15, 4)` |  |
| received_quantity | `decimal(15, 4)` | default(0) |
| unit_id | `unsignedBigInteger` |  |
| unit_service_charge | `decimal(15, 4)` | default(0) |
| total_service_charge | `decimal(15, 4)` | default(0) |
| scrap_quantity | `decimal(15, 4)` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('order_id', 'scol_order_idx')`

Foreign keys:

- `$table->foreign('order_id')->references('id')->on('subcontract_orders')->onDelete('cascade')`
- `$table->foreign('product_id')->references('id')->on('products')->onDelete('restrict')`
- `$table->foreign('variant_id')->references('id')->on('product_variants')->onDelete('set null')`
- `$table->foreign('unit_id')->references('id')->on('units_of_measure')`

### subcontract_receipt_lines

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| receipt_id | `unsignedBigInteger` |  |
| order_line_id | `unsignedBigInteger` |  |
| product_id | `unsignedBigInteger` |  |
| quantity_received | `decimal(15, 4)` |  |
| quantity_rejected | `decimal(15, 4)` | default(0) |
| unit_id | `unsignedBigInteger` |  |
| unit_cost | `decimal(15, 4)` | default(0) |
| total_cost | `decimal(15, 4)` | default(0) |
| batch_number | `string(100)` | nullable |
| expiry_date | `date` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('receipt_id', 'scrl_receipt_idx')`

Foreign keys:

- `$table->foreign('receipt_id')->references('id')->on('subcontract_receipts')->onDelete('cascade')`
- `$table->foreign('order_line_id')->references('id')->on('subcontract_order_lines')->onDelete('restrict')`
- `$table->foreign('product_id')->references('id')->on('products')->onDelete('restrict')`
- `$table->foreign('unit_id')->references('id')->on('units_of_measure')`

### subcontract_transfers

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| order_id | `unsignedBigInteger` |  |
| transfer_date | `date` |  |
| transfer_type | `enum(['outward', 'inward'])` |  |
| warehouse_id | `unsignedBigInteger` |  |
| notes | `text` | nullable |
| created_by | `unsignedBigInteger` |  |
| stock_movement_id | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['order_id', 'transfer_type'], 'sct_order_type_idx')`

Foreign keys:

- `$table->foreign('order_id')->references('id')->on('subcontract_orders')->onDelete('cascade')`
- `$table->foreign('warehouse_id')->references('id')->on('warehouses')`
- `$table->foreign('created_by')->references('id')->on('users')`
- `$table->foreign('stock_movement_id')->references('id')->on('stock_movements')->onDelete('set null')`

### subcontract_transfer_lines

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| transfer_id | `unsignedBigInteger` |  |
| product_id | `unsignedBigInteger` |  |
| variant_id | `unsignedBigInteger` | nullable |
| component_line_id | `unsignedBigInteger` | nullable |
| quantity | `decimal(15, 4)` |  |
| unit_id | `unsignedBigInteger` |  |
| batch_number | `string(100)` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('transfer_id', 'sctl_transfer_idx')`

Foreign keys:

- `$table->foreign('transfer_id')->references('id')->on('subcontract_transfers')->onDelete('cascade')`
- `$table->foreign('product_id')->references('id')->on('products')->onDelete('restrict')`
- `$table->foreign('variant_id')->references('id')->on('product_variants')->onDelete('set null')`
- `$table->foreign('component_line_id')->references('id')->on('subcontract_components')->onDelete('set null')`
- `$table->foreign('unit_id')->references('id')->on('units_of_measure')`

### supplier_ncr_records

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| ncr_number | `string(50)` | unique |
| supplier_id | `unsignedBigInteger` |  |
| product_id | `unsignedBigInteger` | nullable |
| po_number | `string(50)` | nullable |
| nonconformance_description | `text` |  |
| severity | `enum(['critical', 'major', 'minor'])` | default('minor') |
| disposition | `enum(['use_as_is', 'rework', 'repair', 'return_to_supplier', 'scrap'])` | nullable |
| status | `enum(['open', 'supplier_response_pending', 'under_review', 'closed'])` | default('open') |
| detected_date | `date` |  |
| closed_date | `date` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete()`
- `$table->foreign('supplier_id', 'sq_ncr_supplier_fk')->references('id')->on('contacts')->cascadeOnDelete()`
- `$table->foreign('product_id', 'sq_ncr_product_fk')->references('id')->on('products')->nullOnDelete()`

### usage_decisions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| inspection_lot_id | `foreignId` | constrained('inspection_lots'), cascadeOnDelete |
| decision_number | `string` | unique |
| decision_code | `enum(['accept', 'reject', 'partial'])` | default('accept') |
| qty_unrestricted | `decimal(12, 4)` | default(0) — → movement 321 |
| qty_blocked | `decimal(12, 4)` | default(0) — → movement 346 |
| qty_scrap | `decimal(12, 4)` | default(0) — → movement 551 |
| notes | `text` | nullable |
| decided_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| decided_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'inspection_lot_id'])`

## 0390_purchase_3.php

### bill_lines

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| bill_id | `foreignId` | constrained, cascadeOnDelete |
| product_id | `foreignId` | nullable, constrained, nullOnDelete |
| variant_id | `foreignId` | nullable, constrained('product_variants'), nullOnDelete |
| description | `text` |  |
| quantity | `decimal(18, 4)` |  |
| unit_id | `foreignId` | nullable, constrained('units_of_measure'), nullOnDelete |
| unit_price | `decimal(18, 4)` |  |
| discount_type | `enum(['percentage', 'fixed'])` | nullable |
| discount_value | `decimal(18, 4)` | default(0) |
| discount_amount | `decimal(18, 4)` | default(0) |
| tax_category_id | `foreignId` | nullable, constrained('tax_categories'), nullOnDelete |
| tax_rate | `decimal(8, 4)` | default(0) |
| tax_amount | `decimal(18, 4)` | default(0) |
| tax_code | `string(10)` | nullable |
| cgst_rate | `decimal(8, 4)` | default(0) |
| cgst_amount | `decimal(18, 4)` | default(0) |
| sgst_rate | `decimal(8, 4)` | default(0) |
| sgst_amount | `decimal(18, 4)` | default(0) |
| igst_rate | `decimal(8, 4)` | default(0) |
| igst_amount | `decimal(18, 4)` | default(0) |
| hsn_code | `string(20)` | nullable |
| subtotal | `decimal(18, 4)` | default(0) |
| total | `decimal(18, 4)` | default(0) |
| account_id | `foreignId` | nullable, constrained('chart_of_accounts'), nullOnDelete |
| warehouse_id | `foreignId` | nullable, constrained, nullOnDelete |
| line_order | `unsignedSmallInteger` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('bill_id')`

### contract_lines

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| contract_id | `unsignedBigInteger` |  |
| product_id | `unsignedBigInteger` | nullable |
| description | `string(500)` |  |
| quantity | `decimal(15, 4)` | nullable |
| unit_price | `decimal(15, 4)` | nullable |
| line_total | `decimal(15, 4)` | nullable |
| unit_id | `unsignedBigInteger` | nullable |
| delivery_schedule | `json` | nullable |
| sort_order | `smallInteger` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('contract_id', 'contract_lines_contract_id_idx')`

Foreign keys:

- `$table->foreign('contract_id')->references('id')->on('contracts')->onDelete('cascade')`
- `$table->foreign('product_id')->references('id')->on('products')->nullOnDelete()`
- `$table->foreign('unit_id')->references('id')->on('units_of_measure')->nullOnDelete()`

### goods_receipts

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| gr_number | `string(30)` |  |
| purchase_order_id | `unsignedBigInteger` | nullable |
| contact_id | `unsignedBigInteger` | nullable |
| gr_date | `date` |  |
| warehouse_id | `unsignedBigInteger` |  |
| status | `enum(['draft', 'posted', 'reversed'])` | default('draft') |
| reversal_reason | `text` | nullable |
| reversed_at | `timestamp` | nullable |
| journal_entry_id | `unsignedBigInteger` | nullable |
| notes | `text` | nullable |
| created_by | `unsignedBigInteger` |  |
| branch_id | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |
| inspection_lot_id | `unsignedBigInteger` | nullable |

Indexes:

- `$table->unique(['organization_id', 'gr_number'], 'goods_receipts_org_number_unique')`
- `$table->index(['organization_id', 'status'], 'goods_receipts_org_status_idx')`
- `$table->index(['organization_id', 'gr_date'], 'goods_receipts_org_date_idx')`

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade')`
- `$table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->nullOnDelete()`
- `$table->foreign('contact_id')->references('id')->on('contacts')->nullOnDelete()`
- `$table->foreign('warehouse_id')->references('id')->on('warehouses')`
- `$table->foreign('created_by')->references('id')->on('users')`
- `$table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete()`
- `$table->foreign('inspection_lot_id')->references('id')->on('inspection_lots')->nullOnDelete()`

Added by later migrations:

- `0490_goods_receipt_inspection_status.php`: `$table->enum('status', ['draft', 'in_inspection', 'posted', 'reversed'])->default('draft')->change()`

### ers_run_items

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `unsignedBigInteger` |  |
| ers_run_id | `unsignedBigInteger` |  |
| goods_receipt_id | `unsignedBigInteger` |  |
| bill_id | `unsignedBigInteger` | nullable |
| vendor_id | `unsignedBigInteger` |  |
| gross_amount | `decimal(18, 4)` |  |
| status | `enum(['processed', 'failed', 'skipped'])` | default('processed') |
| error_message | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['ers_run_id'], 'ers_item_run_idx')`

Foreign keys:

- `$table->foreign('organization_id', 'ers_item_org_fk')->references('id')->on('organizations')`
- `$table->foreign('ers_run_id', 'ers_item_run_fk')->references('id')->on('ers_runs')`
- `$table->foreign('goods_receipt_id', 'ers_item_gr_fk')->references('id')->on('goods_receipts')`
- `$table->foreign('bill_id', 'ers_item_bill_fk')->references('id')->on('bills')`
- `$table->foreign('vendor_id', 'ers_item_vendor_fk')->references('id')->on('contacts')`

### outline_agreement_items

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| outline_agreement_id | `unsignedBigInteger` |  |
| product_id | `unsignedBigInteger` | nullable |
| line_number | `unsignedSmallInteger` |  |
| description | `string` | nullable |
| target_quantity | `decimal(18, 4)` | nullable |
| target_value | `decimal(18, 4)` | nullable |
| released_quantity | `decimal(18, 4)` | default(0) |
| released_value | `decimal(18, 4)` | default(0) |
| unit_price | `decimal(18, 4)` | nullable |
| unit_of_measure | `string(20)` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['outline_agreement_id'], 'oai_agreement_idx')`

Foreign keys:

- `$table->foreign('organization_id', 'oai_org_fk')->references('id')->on('organizations')`
- `$table->foreign('outline_agreement_id', 'oai_agreement_fk')->references('id')->on('outline_agreements')`
- `$table->foreign('product_id', 'oai_product_fk')->references('id')->on('products')`

### outline_agreement_releases

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| outline_agreement_id | `unsignedBigInteger` |  |
| outline_agreement_item_id | `unsignedBigInteger` | nullable |
| purchase_order_id | `unsignedBigInteger` | nullable |
| release_date | `date` |  |
| release_quantity | `decimal(18, 4)` | nullable |
| release_value | `decimal(18, 4)` | nullable |
| status | `enum(['open', 'goods_received', 'invoiced', 'cancelled'])` | default('open') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['outline_agreement_id'], 'oar_agreement_idx')`

Foreign keys:

- `$table->foreign('organization_id', 'oar_org_fk')->references('id')->on('organizations')`
- `$table->foreign('outline_agreement_id', 'oar_agreement_fk')->references('id')->on('outline_agreements')`
- `$table->foreign('outline_agreement_item_id', 'oar_item_fk')->references('id')->on('outline_agreement_items')`
- `$table->foreign('purchase_order_id', 'oar_po_fk')->references('id')->on('purchase_orders')`

### purchase_order_lines

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| purchase_order_id | `foreignId` | constrained, cascadeOnDelete |
| product_id | `foreignId` | nullable, constrained, nullOnDelete |
| variant_id | `foreignId` | nullable, constrained('product_variants'), nullOnDelete |
| description | `text` |  |
| quantity | `decimal(18, 4)` |  |
| quantity_received | `decimal(18, 4)` | default(0) |
| quantity_billed | `decimal(18, 4)` | default(0) |
| unit_id | `foreignId` | nullable, constrained('units_of_measure'), nullOnDelete |
| unit_price | `decimal(18, 4)` |  |
| discount_type | `enum(['percentage', 'fixed'])` | nullable |
| discount_value | `decimal(18, 4)` | default(0) |
| discount_amount | `decimal(18, 4)` | default(0) |
| tax_category_id | `foreignId` | nullable, constrained('tax_categories'), nullOnDelete |
| tax_rate | `decimal(8, 4)` | default(0) |
| tax_amount | `decimal(18, 4)` | default(0) |
| tax_code | `string(10)` | nullable |
| cgst_rate | `decimal(8, 4)` | default(0) |
| cgst_amount | `decimal(18, 4)` | default(0) |
| sgst_rate | `decimal(8, 4)` | default(0) |
| sgst_amount | `decimal(18, 4)` | default(0) |
| igst_rate | `decimal(8, 4)` | default(0) |
| igst_amount | `decimal(18, 4)` | default(0) |
| subtotal | `decimal(18, 4)` | default(0) |
| total | `decimal(18, 4)` | default(0) |
| warehouse_id | `foreignId` | nullable, constrained, nullOnDelete |
| line_order | `unsignedSmallInteger` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| account_assignment_type | `string(20)` | nullable, comment('K=cost_center, F=order, blank=stock') |

Indexes:

- `$table->index('purchase_order_id', 'pol_purchase_order_id_idx')`
- `$table->index('product_id', 'pol_product_id_idx')`

### goods_receipt_lines

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| gr_id | `unsignedBigInteger` |  |
| po_line_id | `unsignedBigInteger` | nullable |
| product_id | `unsignedBigInteger` |  |
| variant_id | `unsignedBigInteger` | nullable |
| description | `string(500)` |  |
| quantity_ordered | `decimal(15, 4)` |  |
| quantity_received | `decimal(15, 4)` |  |
| quantity_rejected | `decimal(15, 4)` | default(0) |
| unit_id | `unsignedBigInteger` |  |
| unit_cost | `decimal(15, 4)` |  |
| total_cost | `decimal(15, 4)` |  |
| location_id | `unsignedBigInteger` | nullable |
| batch_number | `string(100)` | nullable |
| expiry_date | `date` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('gr_id', 'gr_lines_gr_id_idx')`
- `$table->index('po_line_id', 'gr_lines_po_line_id_idx')`

Foreign keys:

- `$table->foreign('gr_id')->references('id')->on('goods_receipts')->onDelete('cascade')`
- `$table->foreign('po_line_id')->references('id')->on('purchase_order_lines')->nullOnDelete()`
- `$table->foreign('product_id')->references('id')->on('products')`
- `$table->foreign('variant_id')->references('id')->on('product_variants')->nullOnDelete()`
- `$table->foreign('unit_id')->references('id')->on('units_of_measure')`
- `$table->foreign('location_id')->references('id')->on('warehouse_locations')->nullOnDelete()`

### procurement_gr_lines

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| goods_receipt_id | `foreignId` | constrained('procurement_goods_receipts'), cascadeOnDelete |
| po_line_id | `foreignId` | nullable, constrained('purchase_order_lines'), nullOnDelete |
| product_id | `foreignId` | nullable, constrained('products'), nullOnDelete |
| description | `string(500)` | nullable |
| ordered_qty | `decimal(15, 3)` | default(0) |
| received_qty | `decimal(15, 3)` | default(0) |
| accepted_qty | `decimal(15, 3)` | default(0) |
| rejected_qty | `decimal(15, 3)` | default(0) |
| batch_number | `string(100)` | nullable |
| expiry_date | `date` | nullable |
| rejection_reason | `string(500)` | nullable |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('goods_receipt_id', 'proc_gr_lines_gr_id_idx')`
- `$table->index('product_id', 'proc_gr_lines_product_idx')`

### purchase_requisition_lines

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| requisition_id | `foreignId` | constrained('purchase_requisitions'), cascadeOnDelete |
| product_id | `foreignId` | constrained('products') |
| variant_id | `foreignId` | nullable, constrained('product_variants'), nullOnDelete |
| quantity | `decimal(15, 4)` |  |
| uom_id | `foreignId` | nullable, constrained('units_of_measure'), nullOnDelete |
| estimated_unit_price | `decimal(15, 4)` | nullable |
| preferred_vendor_id | `foreignId` | nullable, constrained('contacts'), nullOnDelete |
| warehouse_id | `foreignId` | nullable, constrained('warehouses'), nullOnDelete |
| required_by_date | `date` | nullable |
| notes | `text` | nullable |
| status | `enum(['open', 'partially_converted', 'converted', 'cancelled'])` | default('open') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['requisition_id'], 'prl_req_idx')`
- `$table->index(['product_id', 'status'], 'prl_product_status_idx')`

### purchasing_info_records

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| vendor_id | `unsignedBigInteger` | nullable |
| product_id | `unsignedBigInteger` | nullable |
| warehouse_id | `unsignedBigInteger` | nullable |
| info_category | `enum(['standard', 'subcontracting', 'consignment', 'pipeline'])` | default('standard') |
| is_active | `boolean` | default(true) |
| planned_delivery_days | `unsignedSmallInteger` | nullable |
| reminder_days | `unsignedSmallInteger` | nullable, comment('Days before delivery to send reminder') |
| overdelivery_tolerance | `decimal(5, 2)` | nullable, comment('Over-delivery tolerance percentage') |
| underdelivery_tolerance | `decimal(5, 2)` | nullable, comment('Under-delivery tolerance percentage') |
| is_underdelivery_tolerated | `boolean` | default(false) |
| net_price | `decimal(18, 4)` | nullable |
| price_unit | `unsignedInteger` | default(1) |
| currency_code | `char(3)` | default('SAR') |
| minimum_order_quantity | `decimal(18, 4)` | nullable |
| standard_order_quantity | `decimal(18, 4)` | nullable |
| last_purchase_date | `date` | nullable |
| last_purchase_price | `decimal(18, 4)` | nullable |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'vendor_id', 'product_id', 'info_category'], 'pir_org_vendor_product_category_unq')`
- `$table->index(['organization_id', 'product_id'], 'pir_org_product_idx')`

Foreign keys:

- `$table->foreign('organization_id', 'pir_org_fk')->references('id')->on('organizations')->onDelete('cascade')`
- `$table->foreign('vendor_id', 'pir_vendor_fk')->references('id')->on('contacts')->onDelete('set null')`
- `$table->foreign('product_id', 'pir_product_fk')->references('id')->on('products')->onDelete('set null')`
- `$table->foreign('warehouse_id', 'pir_warehouse_fk')->references('id')->on('warehouses')->onDelete('set null')`

### purchasing_info_record_conditions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| purchasing_info_record_id | `unsignedBigInteger` |  |
| valid_from | `date` |  |
| valid_to | `date` | nullable |
| net_price | `decimal(18, 4)` |  |
| price_unit | `unsignedInteger` | default(1) |
| currency_code | `char(3)` | default('SAR') |
| discount_percent | `decimal(5, 2)` | default(0) |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['purchasing_info_record_id', 'valid_from'], 'pir_cond_pir_valid_idx')`

Foreign keys:

- `$table->foreign('organization_id', 'pir_cond_org_fk')->references('id')->on('organizations')->onDelete('cascade')`
- `$table->foreign('purchasing_info_record_id', 'pir_cond_pir_fk')->references('id')->on('purchasing_info_records')->onDelete('cascade')`

### quota_arrangements

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| product_id | `unsignedBigInteger` |  |
| warehouse_id | `unsignedBigInteger` | nullable |
| valid_from | `date` |  |
| valid_to | `date` | nullable |
| is_active | `boolean` | default(true) |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'product_id', 'valid_from'], 'qa_org_product_valid_idx')`

Foreign keys:

- `$table->foreign('organization_id', 'qa_org_fk')->references('id')->on('organizations')->onDelete('cascade')`
- `$table->foreign('product_id', 'qa_product_fk')->references('id')->on('products')->onDelete('cascade')`
- `$table->foreign('warehouse_id', 'qa_warehouse_fk')->references('id')->on('warehouses')->onDelete('set null')`

### quota_arrangement_items

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| quota_arrangement_id | `unsignedBigInteger` |  |
| vendor_id | `unsignedBigInteger` |  |
| purchasing_info_record_id | `unsignedBigInteger` | nullable |
| quota_percentage | `decimal(5, 2)` | comment('Must sum to 100 across all items in the arrangement') |
| min_lot_size | `decimal(18, 4)` | nullable |
| max_lot_size | `decimal(18, 4)` | nullable |
| allocated_quantity | `decimal(18, 4)` | default(0), comment('Running total of quantity assigned via this quota item') |
| last_assigned_at | `dateTime` | nullable |
| is_blocked | `boolean` | default(false) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['quota_arrangement_id'], 'qai_arrangement_idx')`

Foreign keys:

- `$table->foreign('organization_id', 'qai_org_fk')->references('id')->on('organizations')->onDelete('cascade')`
- `$table->foreign('quota_arrangement_id', 'qai_arrangement_fk')->references('id')->on('quota_arrangements')->onDelete('cascade')`
- `$table->foreign('vendor_id', 'qai_vendor_fk')->references('id')->on('contacts')->onDelete('cascade')`
- `$table->foreign('purchasing_info_record_id', 'qai_pir_fk')->references('id')->on('purchasing_info_records')->onDelete('set null')`

### rfq_items

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| rfq_id | `unsignedBigInteger` |  |
| product_id | `unsignedBigInteger` | nullable |
| description | `string(500)` |  |
| quantity | `decimal(15, 4)` |  |
| unit_id | `unsignedBigInteger` | nullable |
| notes | `text` | nullable |
| sort_order | `smallInteger` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('rfq_id', 'rfq_items_rfq_id_idx')`

Foreign keys:

- `$table->foreign('rfq_id')->references('id')->on('rfq_headers')->onDelete('cascade')`
- `$table->foreign('product_id')->references('id')->on('products')->nullOnDelete()`
- `$table->foreign('unit_id')->references('id')->on('units_of_measure')->nullOnDelete()`

### rfq_lines

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| rfq_id | `foreignId` | constrained('rfq_headers'), cascadeOnDelete |
| product_id | `foreignId` | nullable, constrained('products'), nullOnDelete |
| description | `string(500)` | nullable |
| quantity | `decimal(15, 4)` | default(0) |
| unit_of_measure | `string(20)` | nullable |
| notes | `text` | nullable |
| sort_order | `smallInteger` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('rfq_id', 'rfq_lines_rfq_id_idx')`

### rfq_quote_lines

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| rfq_quote_id | `unsignedBigInteger` |  |
| rfq_item_id | `unsignedBigInteger` |  |
| unit_price | `decimal(15, 4)` |  |
| quantity | `decimal(15, 4)` |  |
| discount_pct | `decimal(5, 2)` | default(0) |
| tax_rate | `decimal(5, 2)` | default(0) |
| line_total | `decimal(15, 4)` |  |
| delivery_days | `unsignedSmallInteger` | nullable |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('rfq_quote_id', 'rfq_quote_lines_quote_id_idx')`

Foreign keys:

- `$table->foreign('rfq_quote_id')->references('id')->on('rfq_quotes')->onDelete('cascade')`
- `$table->foreign('rfq_item_id')->references('id')->on('rfq_items')->onDelete('cascade')`

### rfq_vendor_quotes

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| rfq_vendor_id | `foreignId` | constrained('rfq_vendors'), cascadeOnDelete |
| rfq_line_id | `foreignId` | constrained('rfq_lines'), cascadeOnDelete |
| unit_price | `decimal(15, 4)` | default(0) |
| total_price | `decimal(15, 4)` | default(0) |
| quantity | `decimal(15, 4)` | default(0) |
| discount_pct | `decimal(5, 2)` | default(0) |
| tax_rate | `decimal(5, 2)` | default(0) |
| delivery_days | `unsignedSmallInteger` | nullable |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['rfq_vendor_id', 'rfq_line_id'], 'rfq_vq_vendor_line_idx')`

### scheduling_agreements

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| vendor_id | `unsignedBigInteger` |  |
| product_id | `unsignedBigInteger` |  |
| agreement_number | `string(50)` |  |
| status | `enum(['draft', 'active', 'expired', 'cancelled'])` | default('draft') |
| valid_from | `date` |  |
| valid_to | `date` | nullable |
| target_quantity | `decimal(18, 4)` |  |
| released_quantity | `decimal(18, 4)` | default(0) |
| unit_price | `decimal(18, 4)` |  |
| currency_code | `char(3)` | default('SAR') |
| unit_of_measure | `string(20)` | nullable |
| delivery_days | `unsignedSmallInteger` | default(0) |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'agreement_number'], 'sched_ag_org_number_unq')`
- `$table->index(['organization_id', 'vendor_id', 'product_id'], 'sched_ag_org_vendor_prod_idx')`

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')`
- `$table->foreign('vendor_id', 'sa_vendor_fk')->references('id')->on('contacts')`
- `$table->foreign('product_id', 'sa_product_fk')->references('id')->on('products')`

### sa_delivery_schedules

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| scheduling_agreement_id | `unsignedBigInteger` |  |
| schedule_date | `date` |  |
| scheduled_quantity | `decimal(18, 4)` |  |
| received_quantity | `decimal(18, 4)` | default(0) |
| status | `enum(['open', 'partial', 'complete', 'cancelled'])` | default('open') |
| goods_receipt_id | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['scheduling_agreement_id', 'schedule_date'], 'sads_sa_date_idx')`

Foreign keys:

- `$table->foreign('organization_id', 'sads_org_fk')->references('id')->on('organizations')`
- `$table->foreign('scheduling_agreement_id', 'sads_sa_fk')->references('id')->on('scheduling_agreements')`
- `$table->foreign('goods_receipt_id', 'sads_gr_fk')->references('id')->on('goods_receipts')`

### three_way_match_results

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `unsignedBigInteger` |  |
| bill_id | `unsignedBigInteger` |  |
| bill_line_id | `unsignedBigInteger` | nullable |
| po_line_id | `unsignedBigInteger` | nullable |
| gr_line_id | `unsignedBigInteger` | nullable |
| po_quantity | `decimal(15, 4)` | nullable |
| gr_quantity | `decimal(15, 4)` | nullable |
| invoice_quantity | `decimal(15, 4)` | nullable |
| po_unit_price | `decimal(15, 4)` | nullable |
| invoice_unit_price | `decimal(15, 4)` | nullable |
| quantity_match | `boolean` | default(false) |
| price_match | `boolean` | default(false) |
| match_status | `enum(['matched', 'quantity_variance', 'price_variance', 'missing_gr', 'pending'])` | default('pending') |
| variance_amount | `decimal(15, 4)` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'bill_id'], 'twm_org_bill_idx')`
- `$table->index(['organization_id', 'match_status'], 'twm_org_status_idx')`

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade')`
- `$table->foreign('bill_id')->references('id')->on('bills')->onDelete('cascade')`
- `$table->foreign('po_line_id')->references('id')->on('purchase_order_lines')->nullOnDelete()`
- `$table->foreign('gr_line_id')->references('id')->on('goods_receipt_lines')->nullOnDelete()`

### vendor_consignment_stocks

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string` | unique |
| organization_id | `unsignedBigInteger` |  |
| vendor_id | `unsignedBigInteger` |  |
| product_id | `unsignedBigInteger` |  |
| warehouse_id | `unsignedBigInteger` |  |
| warehouse_location_id | `unsignedBigInteger` | nullable |
| quantity_on_hand | `decimal(18, 4)` | default(0) |
| quantity_reserved | `decimal(18, 4)` | default(0) |
| unit_id | `unsignedBigInteger` | nullable |
| vendor_price | `decimal(18, 4)` |  |
| currency_code | `string(3)` |  |
| last_movement_at | `dateTime` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'vendor_id', 'product_id', 'warehouse_id'], 'vcs_org_vendor_product_wh_unique')`
- `$table->index(['vendor_id', 'product_id'], 'vcs_vendor_product_idx')`

Foreign keys:

- `$table->foreign('organization_id', 'vcs_org_fk')->references('id')->on('organizations')->onDelete('cascade')`
- `$table->foreign('vendor_id', 'vcs_vendor_fk')->references('id')->on('contacts')->onDelete('cascade')`
- `$table->foreign('product_id', 'vcs_product_fk')->references('id')->on('products')->onDelete('cascade')`
- `$table->foreign('warehouse_id', 'vcs_warehouse_fk')->references('id')->on('warehouses')->onDelete('cascade')`
- `$table->foreign('warehouse_location_id', 'vcs_wh_loc_fk')->references('id')->on('warehouse_locations')->onDelete('set null')`
- `$table->foreign('unit_id', 'vcs_unit_fk')->references('id')->on('units_of_measure')->onDelete('set null')`

### vendor_consignment_receipts

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string` | unique |
| organization_id | `unsignedBigInteger` |  |
| vendor_consignment_stock_id | `unsignedBigInteger` |  |
| purchase_order_id | `unsignedBigInteger` | nullable |
| receipt_date | `date` |  |
| quantity_received | `decimal(18, 4)` |  |
| unit_id | `unsignedBigInteger` | nullable |
| vendor_delivery_note | `string(100)` | nullable |
| notes | `text` | nullable |
| created_by | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id', 'vcr_org_fk')->references('id')->on('organizations')->onDelete('cascade')`
- `$table->foreign('vendor_consignment_stock_id', 'vcr_stock_fk')->references('id')->on('vendor_consignment_stocks')->onDelete('cascade')`
- `$table->foreign('purchase_order_id', 'vcr_po_fk')->references('id')->on('purchase_orders')->onDelete('set null')`
- `$table->foreign('unit_id', 'vcr_unit_fk')->references('id')->on('units_of_measure')->onDelete('set null')`
- `$table->foreign('created_by', 'vcr_created_by_fk')->references('id')->on('users')->onDelete('set null')`

### vendor_consignment_withdrawals

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string` | unique |
| organization_id | `unsignedBigInteger` |  |
| vendor_consignment_stock_id | `unsignedBigInteger` |  |
| withdrawal_date | `date` |  |
| quantity_withdrawn | `decimal(18, 4)` |  |
| withdrawal_type | `string(30)` | production/sales/transfer/scrapping |
| reference_type | `string(50)` | nullable |
| reference_id | `unsignedBigInteger` | nullable |
| unit_id | `unsignedBigInteger` | nullable |
| notes | `text` | nullable |
| created_by | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id', 'vcw_org_fk')->references('id')->on('organizations')->onDelete('cascade')`
- `$table->foreign('vendor_consignment_stock_id', 'vcw_stock_fk')->references('id')->on('vendor_consignment_stocks')->onDelete('cascade')`
- `$table->foreign('unit_id', 'vcw_unit_fk')->references('id')->on('units_of_measure')->onDelete('set null')`
- `$table->foreign('created_by', 'vcw_created_by_fk')->references('id')->on('users')->onDelete('set null')`

### vendor_contract_items

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| vendor_contract_id | `foreignId` | constrained('vendor_contracts'), cascadeOnDelete |
| product_id | `foreignId` | nullable, constrained('products'), nullOnDelete |
| description | `string(500)` | nullable |
| quantity | `decimal(15, 3)` | default(0) |
| unit_price | `decimal(15, 4)` | default(0) |
| unit_of_measure | `string(20)` | nullable |
| notes | `text` | nullable |
| sort_order | `smallInteger` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('vendor_contract_id', 'vendor_contract_items_contract_idx')`

### vendor_credit_note_lines

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| vendor_credit_note_id | `unsignedBigInteger` |  |
| product_id | `unsignedBigInteger` | nullable |
| description | `string(500)` |  |
| quantity | `decimal(15, 4)` |  |
| unit_price | `decimal(15, 4)` |  |
| tax_rate | `decimal(5, 2)` | default(0) |
| tax_amount | `decimal(15, 4)` | default(0) |
| line_total | `decimal(15, 4)` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade')`
- `$table->foreign('vendor_credit_note_id')->references('id')->on('vendor_credit_notes')->onDelete('cascade')`
- `$table->foreign('product_id')->references('id')->on('products')->nullOnDelete()`

### vendor_product_pricing

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` | index |
| product_id | `unsignedBigInteger` |  |
| vendor_id | `unsignedBigInteger` |  |
| vendor_product_code | `string(100)` | nullable |
| vendor_product_description | `string(500)` | nullable |
| unit_price | `decimal(15, 4)` |  |
| currency_code | `char(3)` | default('SAR') |
| lead_time_days | `unsignedInteger` | default(7) |
| minimum_order_quantity | `decimal(10, 4)` | default(1) |
| order_quantity_multiple | `decimal(10, 4)` | nullable, comment('Quantity must be ordered in multiples of this value') |
| valid_from | `date` | nullable |
| valid_to | `date` | nullable |
| is_preferred_vendor | `boolean` | default(false) |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'product_id', 'vendor_id'], 'vpp_org_product_vendor_idx')`
- `$table->index(['organization_id', 'product_id', 'is_preferred_vendor'], 'vpp_org_product_preferred_idx')`

Foreign keys:

- `$table->foreign('product_id')->references('id')->on('products')->onDelete('cascade')`
- `$table->foreign('vendor_id')->references('id')->on('contacts')->onDelete('cascade')`

### vendor_source_lists

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` | index |
| product_id | `unsignedBigInteger` |  |
| vendor_id | `unsignedBigInteger` |  |
| vendor_product_pricing_id | `unsignedBigInteger` | nullable |
| plant_code | `string(50)` | nullable |
| valid_from | `date` | nullable |
| valid_to | `date` | nullable |
| is_fixed_vendor | `boolean` | default(false) |
| is_blocked | `boolean` | default(false) |
| priority | `unsignedInteger` | default(1) |
| quota_percentage | `decimal(5, 2)` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'product_id', 'is_blocked', 'priority'], 'vsl_org_product_blocked_priority_idx')`

Foreign keys:

- `$table->foreign('product_id')->references('id')->on('products')->onDelete('cascade')`
- `$table->foreign('vendor_id')->references('id')->on('contacts')->onDelete('cascade')`
- `$table->foreign('vendor_product_pricing_id')->references('id')->on('vendor_product_pricing')->onDelete('set null')`

## 0400_sales_4.php

### atp_checks

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| source_document_id | `unsignedBigInteger` |  |
| source_document_type | `string(30)` |  |
| product_id | `foreignId` | constrained('products') |
| warehouse_id | `foreignId` | nullable, constrained('warehouses'), nullOnDelete |
| requested_quantity | `decimal(15, 4)` |  |
| confirmed_quantity | `decimal(15, 4)` | default(0) |
| requested_date | `date` |  |
| confirmed_date | `date` | nullable |
| availability_breakdown | `json` | nullable |
| result | `enum(['full', 'partial', 'none'])` | default('none') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['source_document_type', 'source_document_id'], 'atp_doc_idx')`
- `$table->index(['organization_id', 'product_id'], 'atp_org_product_idx')`

### bulk_sale_items

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| batch_id | `foreignId` | constrained('bulk_sale_batches'), cascadeOnDelete |
| line_number | `unsignedInteger` |  |
| customer_id | `foreignId` | nullable, constrained('contacts'), nullOnDelete |
| customer_name | `string` | nullable |
| customer_email | `string` | nullable |
| customer_phone | `string` | nullable |
| customer_tax_number | `string` | nullable |
| product_id | `foreignId` | nullable, constrained('products'), nullOnDelete |
| description | `string` |  |
| quantity | `decimal(15, 4)` | default(1) |
| unit_price | `decimal(15, 4)` |  |
| discount_amount | `decimal(15, 2)` | default(0) |
| tax_rate | `decimal(5, 2)` | default(0) |
| tax_amount | `decimal(15, 2)` | default(0) |
| total_amount | `decimal(15, 2)` |  |
| payment_status | `string(20)` | default('unpaid') — unpaid, paid, partial |
| amount_paid | `decimal(15, 2)` | default(0) |
| payment_reference | `string` | nullable |
| status | `string(20)` | default('pending') — pending, processing, completed, failed, skipped |
| invoice_id | `foreignId` | nullable, constrained('invoices'), nullOnDelete |
| payment_id | `foreignId` | nullable — payments_received |
| error_message | `text` | nullable |
| processed_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['batch_id', 'status'])`

Foreign keys:

- `$table->foreign('payment_id', 'bulk_sale_item_payment_fk')->references('id')->on('payments_received')->nullOnDelete()`

### cash_sale_lines

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| cash_sale_id | `unsignedBigInteger` |  |
| product_id | `unsignedBigInteger` |  |
| quantity | `decimal(18, 4)` |  |
| uom | `string(20)` |  |
| unit_price | `decimal(18, 4)` |  |
| discount_pct | `decimal(8, 4)` | default(0) |
| line_total | `decimal(18, 4)` |  |
| tax_amount | `decimal(18, 4)` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('cash_sale_id', 'cs_line_fk')->references('id')->on('cash_sales')->onDelete('cascade')`
- `$table->foreign('product_id', 'cs_line_prod_fk')->references('id')->on('products')->onDelete('restrict')`

### consignment_order_lines

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| order_id | `foreignId` | constrained('consignment_orders'), cascadeOnDelete |
| product_id | `foreignId` | constrained('products') |
| variant_id | `foreignId` | nullable, constrained('product_variants'), nullOnDelete |
| quantity | `decimal(15, 4)` |  |
| unit_id | `foreignId` | constrained('units_of_measure') |
| unit_price | `decimal(15, 4)` | nullable |
| tax_rate | `decimal(5, 2)` | default(0) |
| line_total | `decimal(15, 4)` | nullable |
| warehouse_id | `foreignId` | nullable, constrained('warehouses'), nullOnDelete |
| notes | `string(200)` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['order_id'], 'col_order_idx')`

### consignment_stocks

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| contact_id | `foreignId` | constrained('contacts'), cascadeOnDelete |
| product_id | `foreignId` | constrained('products'), cascadeOnDelete |
| variant_id | `foreignId` | nullable, constrained('product_variants'), nullOnDelete |
| warehouse_id | `foreignId` | nullable, constrained('warehouses'), nullOnDelete |
| on_hand_quantity | `decimal(15, 4)` | default(0) |
| last_updated_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'contact_id', 'product_id', 'variant_id', 'warehouse_id'], 'cs_org_contact_prod_variant_wh_unique')`
- `$table->index(['organization_id', 'contact_id'], 'cs_org_contact_idx')`

### consignment_movements

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| consignment_stock_id | `foreignId` | constrained('consignment_stocks'), cascadeOnDelete |
| order_id | `foreignId` | constrained('consignment_orders'), cascadeOnDelete |
| movement_type | `enum(['in', 'out'])` |  |
| quantity | `decimal(15, 4)` |  |
| balance_after | `decimal(15, 4)` |  |
| moved_at | `timestamp` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['consignment_stock_id'], 'cm_stock_idx')`
- `$table->index(['order_id'], 'cm_order_idx')`

### cpq_configurable_products

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| product_id | `foreignId` | constrained('products'), cascadeOnDelete |
| name | `string` |  |
| description | `text` | nullable |
| base_price | `decimal(18, 4)` | default(0) |
| currency_code | `string(3)` |  |
| configuration_validity_days | `integer` | default(30) |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'is_active'], 'cpq_prod_org_active_idx')`

### cpq_configurations

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| cpq_configurable_product_id | `foreignId` | constrained('cpq_configurable_products'), cascadeOnDelete |
| contact_id | `foreignId` | nullable, constrained('contacts'), nullOnDelete |
| quotation_id | `foreignId` | nullable, constrained('quotations'), nullOnDelete |
| configuration_code | `string(30)` |  |
| status | `string(20)` | default('draft') — draft\|valid\|expired\|converted |
| total_price | `decimal(18, 4)` |  |
| currency_code | `string(3)` |  |
| valid_until | `date` | nullable |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['contact_id', 'status'], 'cpq_cfg_contact_status_idx')`
- `$table->index(['status', 'valid_until'], 'cpq_cfg_status_valid_idx')`

### cpq_option_groups

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| cpq_configurable_product_id | `foreignId` | constrained('cpq_configurable_products'), cascadeOnDelete |
| group_code | `string(30)` |  |
| name | `string` |  |
| selection_type | `string(20)` | default('single') — single\|multi |
| is_required | `boolean` | default(false) |
| sort_order | `integer` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

### cpq_options

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| cpq_option_group_id | `foreignId` | constrained('cpq_option_groups'), cascadeOnDelete |
| option_code | `string(30)` |  |
| name | `string` |  |
| description | `text` | nullable |
| price_modifier_type | `string(20)` | default('none') — fixed\|percentage\|none |
| price_modifier_value | `decimal(18, 4)` | default(0) |
| is_default | `boolean` | default(false) |
| is_active | `boolean` | default(true) |
| sort_order | `integer` | default(0) |
| linked_product_id | `foreignId` | nullable, constrained('products'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

### cpq_configuration_items

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| cpq_configuration_id | `foreignId` | constrained('cpq_configurations'), cascadeOnDelete |
| cpq_option_group_id | `foreignId` | constrained('cpq_option_groups'), cascadeOnDelete |
| cpq_option_id | `foreignId` | constrained('cpq_options'), cascadeOnDelete |
| quantity | `decimal(18, 4)` | default(1) |
| unit_price | `decimal(18, 4)` |  |
| line_total | `decimal(18, 4)` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

### cpq_constraint_rules

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| cpq_configurable_product_id | `foreignId` | constrained('cpq_configurable_products'), cascadeOnDelete |
| rule_type | `string(20)` | requires\|excludes\|includes |
| if_option_id | `foreignId` | nullable, constrained('cpq_options'), nullOnDelete |
| then_option_id | `foreignId` | nullable, constrained('cpq_options'), nullOnDelete |
| error_message | `string(200)` | nullable |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

### cpq_pricing_rules

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| cpq_configurable_product_id | `foreignId` | constrained('cpq_configurable_products'), cascadeOnDelete |
| rule_name | `string` |  |
| condition_json | `json` |  |
| discount_type | `string(20)` | percentage\|fixed\|price_override |
| discount_value | `decimal(18, 4)` |  |
| priority | `integer` | default(50) |
| valid_from | `date` | nullable |
| valid_to | `date` | nullable |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

### customer_material_infos

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| contact_id | `foreignId` | constrained('contacts'), cascadeOnDelete |
| product_id | `foreignId` | constrained('products'), cascadeOnDelete |
| customer_material_number | `string(100)` | nullable |
| customer_material_description | `string(255)` | nullable |
| delivery_lead_time_days | `integer` | default(0) |
| minimum_order_quantity | `decimal(15, 4)` | nullable |
| unit_of_measure | `string(20)` | nullable |
| notes | `text` | nullable |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'contact_id', 'product_id'], 'cmi_org_contact_product_unique')`
- `$table->index(['contact_id', 'customer_material_number'], 'cmi_contact_mat_num_idx')`

### debit_note_items

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| debit_note_id | `foreignId` | constrained('debit_notes'), cascadeOnDelete |
| product_id | `foreignId` | nullable, constrained('products'), nullOnDelete |
| account_id | `foreignId` | nullable, constrained('chart_of_accounts'), nullOnDelete |
| description | `string(500)` | nullable |
| quantity | `decimal(15, 4)` | default(1) |
| unit_price | `decimal(15, 4)` | default(0) |
| tax_rate | `decimal(5, 2)` | default(0) |
| tax_amount | `decimal(15, 2)` | default(0) |
| subtotal | `decimal(15, 2)` | default(0) |
| total | `decimal(15, 2)` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('debit_note_id', 'debit_note_items_dn_id_idx')`

### exchange_order_items

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| exchange_order_id | `foreignId` | constrained('exchange_orders'), cascadeOnDelete |
| original_product_id | `foreignId` | constrained('products'), cascadeOnDelete — What was returned |
| replacement_product_id | `foreignId` | constrained('products'), cascadeOnDelete — What they get |
| replacement_variant_id | `foreignId` | nullable, constrained('product_variants'), nullOnDelete |
| original_quantity | `decimal(15, 4)` |  |
| replacement_quantity | `decimal(15, 4)` |  |
| original_unit_price | `decimal(15, 4)` |  |
| replacement_unit_price | `decimal(15, 4)` |  |
| price_difference | `decimal(15, 2)` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['exchange_order_id'])`

### free_goods_conditions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| condition_number | `string` | unique |
| customer_id | `unsignedBigInteger` | nullable |
| customer_group_id | `unsignedBigInteger` | nullable |
| product_id | `unsignedBigInteger` |  |
| free_product_id | `unsignedBigInteger` | nullable |
| free_goods_type | `enum(['inclusive', 'exclusive'])` | default('exclusive') |
| minimum_quantity | `decimal(18, 4)` |  |
| free_quantity | `decimal(18, 4)` |  |
| calculation_type | `enum(['quantity', 'percentage'])` | default('quantity') |
| valid_from | `date` |  |
| valid_to | `date` | nullable |
| active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade')`
- `$table->foreign('customer_id', 'fg_cond_cust_fk')->references('id')->on('contacts')->onDelete('set null')`
- `$table->foreign('customer_group_id', 'fg_cond_cg_fk')->references('id')->on('customer_groups')->onDelete('set null')`
- `$table->foreign('product_id', 'fg_cond_prod_fk')->references('id')->on('products')->onDelete('cascade')`
- `$table->foreign('free_product_id', 'fg_cond_free_prod_fk')->references('id')->on('products')->onDelete('set null')`

### intercompany_sales_order_lines

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| intercompany_sales_order_id | `unsignedBigInteger` |  |
| product_id | `unsignedBigInteger` |  |
| line_number | `unsignedSmallInteger` |  |
| description | `string` | nullable |
| quantity | `decimal(18, 4)` |  |
| unit_of_measure | `string(20)` | nullable |
| transfer_price | `decimal(18, 4)` |  |
| list_price | `decimal(18, 4)` | nullable |
| tax_rate | `decimal(5, 2)` | default(0) |
| tax_amount | `decimal(18, 4)` | default(0) |
| line_total | `decimal(18, 4)` |  |
| delivered_quantity | `decimal(18, 4)` | default(0) |
| billed_quantity | `decimal(18, 4)` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['intercompany_sales_order_id'], 'icsol_order_idx')`

Foreign keys:

- `$table->foreign('intercompany_sales_order_id', 'icsol_order_fk')->references('id')->on('intercompany_sales_orders')->cascadeOnDelete()`
- `$table->foreign('product_id', 'icsol_product_fk')->references('id')->on('products')->restrictOnDelete()`

### invoice_lines

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| invoice_id | `foreignId` | constrained, cascadeOnDelete |
| product_id | `foreignId` | nullable, constrained, nullOnDelete |
| variant_id | `foreignId` | nullable, constrained('product_variants'), nullOnDelete |
| description | `text` |  |
| quantity | `decimal(18, 4)` |  |
| unit_id | `foreignId` | nullable, constrained('units_of_measure'), nullOnDelete |
| unit_price | `decimal(18, 4)` |  |
| discount_type | `enum(['percentage', 'fixed'])` | nullable |
| discount_value | `decimal(18, 4)` | default(0) |
| discount_amount | `decimal(18, 4)` | default(0) |
| tax_category_id | `foreignId` | nullable, constrained('tax_categories'), nullOnDelete |
| tax_rate | `decimal(8, 4)` | default(0) |
| tax_amount | `decimal(18, 4)` | default(0) |
| tax_code | `string(10)` | nullable — S, Z, E, O |
| cgst_rate | `decimal(8, 4)` | default(0) |
| cgst_amount | `decimal(18, 4)` | default(0) |
| sgst_rate | `decimal(8, 4)` | default(0) |
| sgst_amount | `decimal(18, 4)` | default(0) |
| igst_rate | `decimal(8, 4)` | default(0) |
| igst_amount | `decimal(18, 4)` | default(0) |
| hsn_code | `string(20)` | nullable |
| subtotal | `decimal(18, 4)` | default(0) — Before tax |
| total | `decimal(18, 4)` | default(0) — After tax |
| account_id | `foreignId` | nullable, constrained('chart_of_accounts'), nullOnDelete |
| warehouse_id | `foreignId` | nullable, constrained, nullOnDelete |
| line_order | `unsignedSmallInteger` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| original_price | `decimal(15, 4)` | nullable |
| price_overridden | `boolean` | default(false) |
| override_reason | `string(100)` | nullable |
| override_approved_by | `foreignId` | nullable |
| tax_exemption_code | `string(30)` | nullable |
| tax_exemption_reason | `string(255)` | nullable |

Indexes:

- `$table->index('invoice_id')`
- `$table->index('product_id')`

### credit_note_items

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| credit_note_id | `foreignId` | constrained, cascadeOnDelete |
| product_id | `foreignId` | nullable, constrained('products'), nullOnDelete |
| original_invoice_line_id | `foreignId` | nullable — Link to original invoice line |
| description | `text` |  |
| quantity | `decimal(15, 4)` |  |
| unit_id | `foreignId` | nullable |
| unit_price | `decimal(15, 4)` |  |
| discount_amount | `decimal(15, 2)` | default(0) |
| tax_code | `string(10)` | nullable |
| tax_rate | `decimal(5, 2)` | default(0) |
| tax_amount | `decimal(15, 2)` | default(0) |
| subtotal | `decimal(15, 2)` |  |
| total | `decimal(15, 2)` |  |
| account_id | `foreignId` | nullable, constrained('chart_of_accounts'), nullOnDelete |
| line_order | `unsignedTinyInteger` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['credit_note_id', 'line_order'])`

Foreign keys:

- `$table->foreign('original_invoice_line_id', 'cn_item_orig_inv_line_fk')->references('id')->on('invoice_lines')->nullOnDelete()`
- `$table->foreign('unit_id', 'cn_item_unit_fk')->references('id')->on('units_of_measure')->nullOnDelete()`

### price_list_items

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| price_list_id | `foreignId` | constrained, cascadeOnDelete |
| product_id | `foreignId` | constrained, cascadeOnDelete |
| unit_price | `decimal(15, 4)` |  |
| min_quantity | `decimal(15, 4)` | default(1) — For bulk pricing tiers |
| max_quantity | `decimal(15, 4)` | nullable |
| discount_percent | `decimal(5, 2)` | default(0) |
| valid_from | `date` | nullable |
| valid_until | `date` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['price_list_id', 'product_id', 'min_quantity'])`
- `$table->index(['product_id', 'min_quantity'])`

### price_overrides

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| document_type | `string(100)` | nullable — Invoice, Quotation, SalesOrder, Bill, PurchaseOrder |
| document_id | `unsignedBigInteger` | nullable |
| line_item_id | `unsignedBigInteger` | nullable — The specific line item |
| product_id | `foreignId` | nullable, constrained('products'), nullOnDelete |
| variant_id | `foreignId` | nullable, constrained('product_variants'), nullOnDelete |
| original_price | `decimal(15, 4)` | default(0) — List/catalog price |
| override_price | `decimal(15, 4)` | default(0) — New price set at billing |
| cost_price | `decimal(15, 4)` | nullable — Product cost (for margin check) |
| price_difference | `decimal(15, 4)` | default(0) — override - original |
| discount_percent | `decimal(5, 2)` | default(0) — % change |
| quantity | `decimal(15, 4)` | default(1) |
| total_impact | `decimal(15, 2)` | default(0) — Total monetary impact |
| override_type | `string(30)` | nullable — discount, markup, custom_price, price_match, negotiated, manager_override |
| reason_code | `string(30)` | nullable — competitor_match, bulk_order, loyalty, damaged, negotiated, clearance |
| reason | `text` | nullable — Free-text reason |
| notes | `text` | nullable |
| approval_status | `string(20)` | default('auto_approved') — auto_approved, pending, approved, rejected |
| approved_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| approved_at | `timestamp` | nullable |
| approval_notes | `text` | nullable |
| policy_id | `foreignId` | nullable, constrained('price_override_policies'), nullOnDelete |
| customer_id | `foreignId` | nullable, constrained('contacts'), nullOnDelete |
| margin_before | `decimal(5, 2)` | nullable — Margin % at original price |
| margin_after | `decimal(5, 2)` | nullable — Margin % at override price |
| created_by | `foreignId` | constrained('users'), cascadeOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'created_at'])`
- `$table->index(['document_type', 'document_id'])`
- `$table->index(['product_id'])`
- `$table->index(['created_by', 'created_at'])`
- `$table->index(['approval_status'])`
- `$table->index(['override_type'])`

### price_volume_breaks

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| price_list_id | `foreignId` | constrained('price_lists'), cascadeOnDelete |
| product_id | `foreignId` | constrained('products'), cascadeOnDelete |
| variant_id | `foreignId` | nullable, constrained('product_variants'), nullOnDelete |
| min_qty | `decimal(15, 4)` |  |
| max_qty | `decimal(15, 4)` | nullable |
| unit_price | `decimal(15, 4)` |  |
| discount_pct | `decimal(5, 2)` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['price_list_id', 'product_id'], 'pvb_list_product_idx')`

### product_attribute_values

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| product_id | `foreignId` | constrained('products'), cascadeOnDelete |
| attribute_id | `foreignId` | constrained('product_attributes'), cascadeOnDelete |
| value_text | `text` | nullable |
| value_number | `decimal(15, 4)` | nullable |
| value_boolean | `boolean` | nullable |
| value_json | `json` | nullable — For multi_select |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['product_id', 'attribute_id'])`

Added by later migrations:

- `0480_mysql_indexes.php`: `CREATE INDEX attr_val_attr_id_value_text_idx ON product_attribute_values (attribute_id, value_text(191))`

### product_bundle_items

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| bundle_id | `foreignId` | constrained('product_bundles'), cascadeOnDelete |
| product_id | `foreignId` | nullable, constrained('products'), nullOnDelete |
| variant_id | `foreignId` | nullable, constrained('product_variants'), nullOnDelete |
| quantity | `decimal(15, 4)` | default(1) |
| description | `text` | nullable |
| original_price | `decimal(15, 4)` | nullable — Individual item price |
| unit_price | `decimal(15, 4)` | nullable — Unit price alias |
| bundle_price | `decimal(15, 4)` | nullable — Override price in bundle |
| discount_percentage | `decimal(8, 4)` | nullable, default(0) — Discount percentage |
| is_optional | `boolean` | default(false) — Customer can choose to exclude |
| is_default_selected | `boolean` | default(true) — Pre-selected for optional items |
| display_order | `unsignedSmallInteger` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['bundle_id', 'display_order'])`

### product_tag_assignments

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| product_id | `foreignId` | constrained('products'), cascadeOnDelete |
| tag_id | `foreignId` | constrained('product_tags'), cascadeOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['product_id', 'tag_id'])`

### purchase_return_items

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| purchase_return_id | `foreignId` | constrained('purchase_returns'), cascadeOnDelete |
| product_id | `foreignId` | nullable, constrained('products'), nullOnDelete |
| bill_item_id | `foreignId` | nullable |
| variant_id | `foreignId` | nullable, constrained('product_variants'), nullOnDelete |
| batch_id | `foreignId` | nullable, constrained('inventory_batches'), nullOnDelete |
| description | `string` | nullable |
| quantity_returned | `decimal(15, 4)` |  |
| unit_price | `decimal(15, 4)` |  |
| tax_rate | `decimal(5, 2)` | default(0) |
| tax_amount | `decimal(15, 2)` | default(0) |
| subtotal | `decimal(15, 2)` |  |
| total | `decimal(15, 2)` |  |
| condition | `string(30)` | nullable — defective, damaged, wrong_item, quality_issue |
| condition_notes | `text` | nullable |
| item_status | `string(20)` | default('pending') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['purchase_return_id'])`
- `$table->index(['product_id'])`

Foreign keys:

- `$table->foreign('bill_item_id', 'pur_ret_item_bill_line_fk')->references('id')->on('bill_lines')->nullOnDelete()`

### quotation_lines

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| quotation_id | `foreignId` | constrained, cascadeOnDelete |
| product_id | `foreignId` | nullable, constrained, nullOnDelete |
| variant_id | `foreignId` | nullable, constrained('product_variants'), nullOnDelete |
| description | `text` |  |
| quantity | `decimal(18, 4)` |  |
| unit_id | `foreignId` | nullable, constrained('units_of_measure'), nullOnDelete |
| unit_price | `decimal(18, 4)` |  |
| discount_type | `enum(['percentage', 'fixed'])` | nullable |
| discount_value | `decimal(18, 4)` | default(0) |
| discount_amount | `decimal(18, 4)` | default(0) |
| tax_category_id | `foreignId` | nullable, constrained('tax_categories'), nullOnDelete |
| tax_rate | `decimal(8, 4)` | default(0) |
| tax_amount | `decimal(18, 4)` | default(0) |
| subtotal | `decimal(18, 4)` | default(0) |
| total | `decimal(18, 4)` | default(0) |
| line_order | `unsignedSmallInteger` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

### rma_items

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| rma_request_id | `foreignId` | constrained('rma_requests'), cascadeOnDelete |
| product_id | `foreignId` | constrained('products'), cascadeOnDelete |
| variant_id | `foreignId` | nullable, constrained('product_variants'), nullOnDelete |
| quantity | `decimal(15, 4)` |  |
| reason | `string(50)` | defective, wrong_item, not_as_described, damaged_in_transit, quality_issue |
| description | `text` | nullable |
| evidence_paths | `json` | nullable — Photos/docs |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['rma_request_id'])`

### sales_order_lines

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| sales_order_id | `foreignId` | constrained, cascadeOnDelete |
| product_id | `foreignId` | nullable, constrained, nullOnDelete |
| variant_id | `foreignId` | nullable, constrained('product_variants'), nullOnDelete |
| description | `text` |  |
| quantity | `decimal(18, 4)` |  |
| quantity_delivered | `decimal(18, 4)` | default(0) |
| quantity_invoiced | `decimal(18, 4)` | default(0) |
| unit_id | `foreignId` | nullable, constrained('units_of_measure'), nullOnDelete |
| unit_price | `decimal(18, 4)` |  |
| discount_type | `enum(['percentage', 'fixed'])` | nullable |
| discount_value | `decimal(18, 4)` | default(0) |
| discount_amount | `decimal(18, 4)` | default(0) |
| tax_category_id | `foreignId` | nullable, constrained('tax_categories'), nullOnDelete |
| tax_rate | `decimal(8, 4)` | default(0) |
| tax_amount | `decimal(18, 4)` | default(0) |
| subtotal | `decimal(18, 4)` | default(0) |
| total | `decimal(18, 4)` | default(0) |
| warehouse_id | `foreignId` | nullable, constrained, nullOnDelete |
| line_order | `unsignedSmallInteger` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

### delivery_document_lines

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| delivery_document_id | `unsignedBigInteger` |  |
| sales_order_line_id | `unsignedBigInteger` | nullable |
| product_id | `unsignedBigInteger` |  |
| delivery_quantity | `decimal(18, 4)` |  |
| picked_quantity | `decimal(18, 4)` | default(0) |
| packed_quantity | `decimal(18, 4)` | default(0) |
| issued_quantity | `decimal(18, 4)` | default(0) |
| uom | `string(20)` |  |
| batch_number | `string` | nullable |
| warehouse_location_id | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('delivery_document_id', 'del_doc_line_fk')->references('id')->on('delivery_documents')->onDelete('cascade')`
- `$table->foreign('sales_order_line_id', 'del_doc_line_sol_fk')->references('id')->on('sales_order_lines')->onDelete('set null')`
- `$table->foreign('product_id', 'del_doc_line_prod_fk')->references('id')->on('products')->onDelete('restrict')`
- `$table->foreign('warehouse_location_id', 'del_doc_line_wloc_fk')->references('id')->on('warehouse_locations')->onDelete('set null')`

### pick_document_lines

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| pick_document_id | `unsignedBigInteger` |  |
| delivery_document_line_id | `unsignedBigInteger` |  |
| required_quantity | `decimal(18, 4)` |  |
| picked_quantity | `decimal(18, 4)` | default(0) |
| storage_bin | `string` | nullable |
| status | `enum(['open', 'partial', 'completed'])` | default('open') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('pick_document_id', 'pick_doc_line_fk')->references('id')->on('pick_documents')->onDelete('cascade')`
- `$table->foreign('delivery_document_line_id', 'pick_doc_line_dl_fk')->references('id')->on('delivery_document_lines')->onDelete('cascade')`

### sales_return_items

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| sales_return_id | `foreignId` | constrained('sales_returns'), cascadeOnDelete |
| product_id | `foreignId` | nullable, constrained('products'), nullOnDelete |
| invoice_item_id | `foreignId` | nullable — Link to original invoice item |
| variant_id | `foreignId` | nullable, constrained('product_variants'), nullOnDelete |
| batch_id | `foreignId` | nullable, constrained('inventory_batches'), nullOnDelete |
| description | `string` | nullable |
| quantity_returned | `decimal(15, 4)` |  |
| quantity_received | `decimal(15, 4)` | default(0) |
| quantity_restocked | `decimal(15, 4)` | default(0) |
| quantity_damaged | `decimal(15, 4)` | default(0) |
| unit_price | `decimal(15, 4)` |  |
| tax_rate | `decimal(5, 2)` | default(0) |
| tax_amount | `decimal(15, 2)` | default(0) |
| subtotal | `decimal(15, 2)` |  |
| total | `decimal(15, 2)` |  |
| condition | `string(30)` | nullable — new, like_new, used, damaged, defective |
| condition_notes | `text` | nullable |
| item_status | `string(20)` | default('pending') — pending, received, inspected, restocked, disposed |
| warehouse_location_id | `foreignId` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['sales_return_id'])`
- `$table->index(['product_id'])`

Foreign keys:

- `$table->foreign('invoice_item_id', 'sales_ret_item_inv_line_fk')->references('id')->on('invoice_lines')->nullOnDelete()`
- `$table->foreign('warehouse_location_id', 'sales_ret_item_wh_location_fk')->references('id')->on('warehouse_locations')->nullOnDelete()`

### shipment_items

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| shipment_id | `foreignId` | constrained('shipments'), cascadeOnDelete |
| product_id | `foreignId` | constrained('products'), cascadeOnDelete |
| variant_id | `foreignId` | nullable, constrained('product_variants'), nullOnDelete |
| quantity | `decimal(15, 4)` |  |
| weight_kg | `decimal(10, 2)` | nullable |
| serial_numbers | `string` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['shipment_id'])`

## 0410_manufacturing_4.php

### work_orders

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| branch_id | `foreignId` | nullable, constrained, nullOnDelete |
| work_order_number | `string(50)` |  |
| bom_template_id | `foreignId` | constrained, cascadeOnDelete |
| sales_order_id | `foreignId` | nullable |
| sales_order_line_id | `foreignId` | nullable |
| product_id | `foreignId` | constrained, cascadeOnDelete |
| variant_id | `foreignId` | nullable, constrained('product_variants'), nullOnDelete |
| planned_quantity | `decimal(15, 4)` |  |
| produced_quantity | `decimal(15, 4)` | default(0) |
| rejected_quantity | `decimal(15, 4)` | default(0) |
| unit_id | `foreignId` | nullable, constrained('units_of_measure'), nullOnDelete |
| planned_start_date | `date` |  |
| planned_end_date | `date` |  |
| actual_start_datetime | `datetime` | nullable |
| actual_end_datetime | `datetime` | nullable |
| source_warehouse_id | `foreignId` | nullable, constrained('warehouses'), nullOnDelete |
| target_warehouse_id | `foreignId` | nullable, constrained('warehouses'), nullOnDelete |
| estimated_material_cost | `decimal(15, 4)` | default(0) |
| estimated_labor_cost | `decimal(15, 4)` | default(0) |
| estimated_overhead_cost | `decimal(15, 4)` | default(0) |
| actual_material_cost | `decimal(15, 4)` | default(0) |
| actual_labor_cost | `decimal(15, 4)` | default(0) |
| actual_overhead_cost | `decimal(15, 4)` | default(0) |
| status | `enum(['draft', 'released', 'in_progress', 'completed', 'closed', 'cancelled'])` | default('draft') |
| priority | `enum(['low', 'normal', 'high', 'urgent'])` | default('normal') |
| assigned_to | `foreignId` | nullable, constrained('users'), nullOnDelete |
| supervisor_id | `foreignId` | nullable, constrained('users'), nullOnDelete |
| notes | `text` | nullable |
| cancellation_reason | `string(500)` | nullable |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'work_order_number'])`
- `$table->index(['organization_id', 'status'])`
- `$table->index(['organization_id', 'planned_start_date'])`

Foreign keys:

- `$table->foreign('sales_order_id', 'wo_sales_order_fk')->references('id')->on('sales_orders')->nullOnDelete()`
- `$table->foreign('sales_order_line_id', 'wo_sales_order_line_fk')->references('id')->on('sales_order_lines')->nullOnDelete()`

### capacity_requirements

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| work_order_id | `foreignId` | constrained('work_orders'), cascadeOnDelete |
| work_center_id | `foreignId` | constrained('work_centers'), cascadeOnDelete |
| operation_id | `unsignedBigInteger` | nullable |
| required_hours | `decimal(8, 2)` |  |
| scheduled_start | `dateTime` | nullable |
| scheduled_end | `dateTime` | nullable |
| status | `enum(['planned', 'scheduled', 'in_progress', 'completed', 'cancelled'])` | default('planned') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['work_center_id', 'status'])`
- `$table->index('organization_id')`

### cost_variances

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| work_order_id | `unsignedBigInteger` |  |
| costing_version_id | `unsignedBigInteger` |  |
| standard_material_cost | `decimal(15, 4)` | default(0) |
| actual_material_cost | `decimal(15, 4)` | default(0) |
| standard_labor_cost | `decimal(15, 4)` | default(0) |
| actual_labor_cost | `decimal(15, 4)` | default(0) |
| standard_overhead_cost | `decimal(15, 4)` | default(0) |
| actual_overhead_cost | `decimal(15, 4)` | default(0) |
| total_standard | `decimal(15, 4)` | default(0) |
| total_actual | `decimal(15, 4)` | default(0) |
| total_variance | `decimal(15, 4)` | default(0) |
| variance_pct | `decimal(7, 2)` | default(0) |
| period_year | `smallInteger` |  |
| period_month | `tinyInteger` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['work_order_id', 'costing_version_id'], 'cvar_wo_version_unique')`
- `$table->index(['organization_id', 'period_year', 'period_month'], 'cvar_org_period_idx')`

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade')`
- `$table->foreign('work_order_id')->references('id')->on('work_orders')->onDelete('cascade')`
- `$table->foreign('costing_version_id')->references('id')->on('costing_versions')->onDelete('cascade')`

### production_logs

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| work_order_id | `foreignId` | constrained, cascadeOnDelete |
| logged_at | `datetime` |  |
| quantity_produced | `decimal(15, 4)` |  |
| quantity_rejected | `decimal(15, 4)` | default(0) |
| rejection_reason | `string(500)` | nullable |
| is_quality_checked | `boolean` | default(false) |
| quality_checked_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| quality_checked_at | `datetime` | nullable |
| quality_parameters | `json` | nullable |
| batch_number | `string(100)` | nullable |
| lot_number | `string(100)` | nullable |
| expiry_date | `date` | nullable |
| stock_movement_id | `foreignId` | nullable |
| notes | `text` | nullable |
| logged_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'work_order_id'])`
- `$table->index(['organization_id', 'logged_at'])`

Foreign keys:

- `$table->foreign('stock_movement_id', 'prod_log_stock_movement_fk')->references('id')->on('stock_movements')->nullOnDelete()`

### production_variances

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| work_order_id | `foreignId` | nullable, constrained('work_orders'), nullOnDelete |
| product_id | `foreignId` | nullable, constrained('products'), nullOnDelete |
| cost_version_id | `foreignId` | nullable, constrained('cost_versions'), nullOnDelete |
| variance_type | `enum(['material', 'labour', 'overhead', 'yield'])` | default('material') |
| standard_cost | `decimal(15, 4)` | default(0) |
| actual_cost | `decimal(15, 4)` | default(0) |
| variance_amount | `decimal(15, 4)` | default(0) |
| variance_pct | `decimal(8, 2)` | default(0) |
| period_date | `date` |  |
| posted_to_gl | `boolean` | default(false) |
| journal_entry_id | `unsignedBigInteger` | nullable |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'period_date'], 'prod_var_org_period_idx')`
- `$table->index(['organization_id', 'variance_type'], 'prod_var_org_type_idx')`
- `$table->index('work_order_id', 'prod_var_wo_idx')`

### scheduling_operations

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| scheduling_board_id | `foreignId` | nullable, constrained('scheduling_boards'), nullOnDelete |
| work_order_id | `foreignId` | nullable, constrained('work_orders'), nullOnDelete |
| process_order_id | `foreignId` | nullable, constrained('process_orders'), nullOnDelete |
| work_center_id | `foreignId` | constrained('work_centers'), cascadeOnDelete |
| operation_number | `unsignedInteger` |  |
| description | `string` |  |
| planned_start | `dateTime` |  |
| planned_finish | `dateTime` |  |
| actual_start | `dateTime` | nullable |
| actual_finish | `dateTime` | nullable |
| duration_minutes | `unsignedInteger` |  |
| setup_minutes | `unsignedInteger` | default(0) |
| teardown_minutes | `unsignedInteger` | default(0) |
| priority | `unsignedInteger` | default(50) |
| is_pinned | `boolean` | default(false) |
| is_fixed | `boolean` | default(false) |
| sequence_number | `unsignedInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['work_center_id', 'planned_start'], 'sched_op_wc_start_idx')`
- `$table->index(['scheduling_board_id'], 'sched_op_board_idx')`
- `$table->index(['work_order_id'], 'sched_op_wo_idx')`
- `$table->index(['priority', 'planned_start'], 'sched_op_prio_start_idx')`

### scheduling_pegging_relationships

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| predecessor_operation_id | `unsignedBigInteger` |  |
| successor_operation_id | `unsignedBigInteger` |  |
| relationship_type | `string(20)` | default('fs') |
| lag_minutes | `integer` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('predecessor_operation_id', 'sched_peg_predecessor_fk')->references('id')->on('scheduling_operations')->cascadeOnDelete()`
- `$table->foreign('successor_operation_id', 'sched_peg_successor_fk')->references('id')->on('scheduling_operations')->cascadeOnDelete()`

### wip_valuations

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| work_order_id | `unsignedBigInteger` |  |
| valuation_date | `date` |  |
| completed_qty | `decimal(15, 4)` | default(0) |
| wip_qty | `decimal(15, 4)` | default(0) |
| material_wip | `decimal(15, 4)` | default(0) |
| labor_wip | `decimal(15, 4)` | default(0) |
| overhead_wip | `decimal(15, 4)` | default(0) |
| total_wip | `decimal(15, 4)` | default(0) |
| journal_entry_id | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['work_order_id', 'valuation_date'], 'wip_wo_date_unique')`
- `$table->index(['organization_id', 'valuation_date'], 'wip_org_date_idx')`

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade')`
- `$table->foreign('work_order_id')->references('id')->on('work_orders')->onDelete('cascade')`
- `$table->foreign('journal_entry_id')->references('id')->on('journal_entries')->onDelete('set null')`

### work_order_materials

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| work_order_id | `foreignId` | constrained, cascadeOnDelete |
| bom_line_id | `foreignId` | nullable, constrained, nullOnDelete |
| product_id | `foreignId` | constrained, cascadeOnDelete |
| variant_id | `foreignId` | nullable, constrained('product_variants'), nullOnDelete |
| description | `string(500)` | nullable |
| required_quantity | `decimal(15, 4)` |  |
| issued_quantity | `decimal(15, 4)` | default(0) |
| consumed_quantity | `decimal(15, 4)` | default(0) |
| returned_quantity | `decimal(15, 4)` | default(0) |
| wastage_quantity | `decimal(15, 4)` | default(0) |
| unit_id | `foreignId` | nullable, constrained('units_of_measure'), nullOnDelete |
| unit_cost | `decimal(15, 4)` | default(0) |
| total_cost | `decimal(15, 4)` | default(0) |
| warehouse_id | `foreignId` | nullable, constrained, nullOnDelete |
| line_order | `unsignedSmallInteger` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['work_order_id', 'product_id'])`

### material_transactions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| work_order_id | `foreignId` | constrained, cascadeOnDelete |
| work_order_material_id | `foreignId` | constrained, cascadeOnDelete |
| transaction_type | `enum(['issue', 'return', 'wastage'])` |  |
| transaction_datetime | `datetime` |  |
| quantity | `decimal(15, 4)` |  |
| unit_cost | `decimal(15, 4)` | default(0) |
| warehouse_id | `foreignId` | constrained |
| stock_movement_id | `foreignId` | nullable |
| reference | `string(100)` | nullable |
| notes | `text` | nullable |
| processed_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'work_order_id'])`
- `$table->index(['organization_id', 'transaction_datetime'])`

Foreign keys:

- `$table->foreign('stock_movement_id', 'mat_txn_stock_movement_fk')->references('id')->on('stock_movements')->nullOnDelete()`

### work_order_operations

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| work_order_id | `foreignId` | constrained, cascadeOnDelete |
| bom_operation_id | `foreignId` | nullable, constrained, nullOnDelete |
| name | `string(100)` |  |
| instructions | `text` | nullable |
| sequence | `unsignedSmallInteger` | default(0) |
| estimated_minutes | `unsignedSmallInteger` | default(0) |
| actual_minutes | `unsignedSmallInteger` | default(0) |
| started_at | `datetime` | nullable |
| completed_at | `datetime` | nullable |
| status | `enum(['pending', 'in_progress', 'completed', 'skipped', ])` | default('pending') |
| assigned_to | `foreignId` | nullable, constrained('users'), nullOnDelete |
| completed_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| scheduled_start | `dateTime` | nullable |
| scheduled_end | `dateTime` | nullable |
| work_center_id | `unsignedBigInteger` | nullable |

Indexes:

- `$table->index(['work_order_id', 'status'])`

Foreign keys:

- `$table->foreign('work_center_id')->references('id')->on('work_centers')->nullOnDelete()`

### activity_confirmations

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string(36)` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| confirmation_number | `string(50)` | unique |
| work_order_id | `foreignId` | nullable, constrained('work_orders', 'id', 'co_act_conf_wo_fk'), nullOnDelete |
| work_center_id | `foreignId` | nullable, constrained('work_centers', 'id', 'co_act_conf_wc_fk'), nullOnDelete |
| cost_center_id | `foreignId` | nullable, constrained('cost_centers', 'id', 'co_act_conf_cc_fk'), nullOnDelete |
| activity_type_id | `foreignId` | nullable, constrained('activity_types', 'id', 'co_act_conf_at_fk'), nullOnDelete |
| confirmed_quantity | `decimal(18, 4)` |  |
| planned_quantity | `decimal(18, 4)` | nullable |
| uom | `string(20)` | default('HR') |
| actual_rate | `decimal(18, 4)` | nullable |
| planned_rate | `decimal(18, 4)` | nullable |
| actual_cost | `decimal(18, 4)` | nullable |
| fiscal_year | `unsignedSmallInteger` |  |
| period | `tinyInteger` | unsigned |
| confirmation_date | `date` |  |
| confirmed_by | `foreignId` | nullable, constrained('users', 'id', 'co_act_conf_by_fk'), nullOnDelete |
| status | `enum(['confirmed', 'reversed'])` | default('confirmed') |
| reversal_id | `unsignedBigInteger` | nullable |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'fiscal_year', 'period'], 'co_act_conf_org_fy_period_idx')`
- `$table->index(['work_order_id'], 'co_act_conf_wo_idx')`
- `$table->index(['cost_center_id', 'activity_type_id'], 'co_act_conf_cc_at_idx')`

Foreign keys:

- `$table->foreign('reversal_id', 'co_act_conf_reversal_fk')->references('id')->on('activity_confirmations')->nullOnDelete()`

## 0420_tm.php

### carriers

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| code | `string(30)` | unique |
| name | `string(200)` |  |
| type | `string(30)` | default('road') — road\|air\|sea\|rail\|courier\|multimodal |
| status | `string(20)` | default('active') — active\|inactive\|suspended |
| scac_code | `string(10)` | nullable — Standard Carrier Alpha Code (road/rail) |
| iata_code | `string(10)` | nullable — IATA carrier code (air) |
| country_code | `string(5)` | nullable |
| currency_code | `string(5)` | default('USD') |
| payment_term_days | `unsignedSmallInteger` | default(30) |
| rating | `decimal(3, 2)` | nullable — 0.00–5.00 computed from performance |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'status'], 'carriers_org_status_idx')`
- `$table->index(['organization_id', 'type', 'status'], 'carriers_org_type_status_idx')`

### carrier_performance

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| carrier_id | `foreignId` | constrained('carriers'), cascadeOnDelete |
| period_year | `unsignedSmallInteger` |  |
| period_month | `unsignedTinyInteger` | 1–12 |
| total_shipments | `unsignedInteger` | default(0) |
| on_time_deliveries | `unsignedInteger` | default(0) |
| late_deliveries | `unsignedInteger` | default(0) |
| damaged_shipments | `unsignedInteger` | default(0) |
| lost_shipments | `unsignedInteger` | default(0) |
| avg_transit_days | `decimal(5, 2)` | nullable |
| cost_variance_pct | `decimal(6, 2)` | nullable — actual vs agreed |
| on_time_pct | `decimal(5, 2)` | nullable — computed |
| rating | `decimal(3, 2)` | nullable — 0.00–5.00 |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'carrier_id', 'period_year', 'period_month'], 'tm_carrier_perf_org_carrier_period_uniq')`

### carrier_services

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| carrier_id | `foreignId` | constrained('carriers'), cascadeOnDelete |
| code | `string(30)` |  |
| name | `string(200)` |  |
| mode | `string(30)` | default('road') — road\|air\|sea\|rail\|courier |
| transit_days_min | `unsignedSmallInteger` | default(1) |
| transit_days_max | `unsignedSmallInteger` | default(1) |
| is_tracking_available | `boolean` | default(false) |
| tracking_url_template | `string(500)` | nullable — {tracking_number} placeholder |
| handles_dangerous_goods | `boolean` | default(false) |
| handles_refrigerated | `boolean` | default(false) |
| handles_oversized | `boolean` | default(false) |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'carrier_id', 'code'], 'carrier_services_org_carrier_code_uniq')`
- `$table->index(['organization_id', 'carrier_id', 'is_active'], 'carrier_services_org_carrier_active_idx')`

### freight_rate_tables

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| code | `string(30)` |  |
| name | `string(200)` |  |
| carrier_id | `foreignId` | nullable, constrained('carriers'), nullOnDelete |
| carrier_service_id | `foreignId` | nullable, constrained('carrier_services'), nullOnDelete |
| valid_from | `date` |  |
| valid_to | `date` | nullable |
| currency_code | `string(5)` | default('USD') |
| basis | `string(20)` | default('weight') — weight\|volume\|piece\|pallet\|shipment |
| is_active | `boolean` | default(true) |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'code'], 'freight_rate_tables_org_code_uniq')`
- `$table->index(['organization_id', 'carrier_id', 'is_active'], 'freight_rate_tables_org_carrier_active_idx')`
- `$table->index(['organization_id', 'valid_from', 'valid_to'], 'freight_rate_tables_org_validity_idx')`

### freight_agreements

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| carrier_id | `foreignId` | constrained('carriers'), cascadeOnDelete |
| agreement_number | `string(50)` |  |
| valid_from | `date` |  |
| valid_to | `date` | nullable |
| currency_code | `string(5)` | default('USD') |
| status | `string(20)` | default('draft') — draft\|active\|expired\|terminated |
| rate_table_id | `foreignId` | nullable, constrained('freight_rate_tables'), nullOnDelete |
| annual_volume_commitment | `decimal(14, 4)` | nullable — kg |
| annual_spend_commitment | `decimal(14, 4)` | nullable |
| payment_term_days | `unsignedSmallInteger` | default(30) |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'agreement_number'], 'freight_agreements_org_num_uniq')`
- `$table->index(['organization_id', 'carrier_id', 'status'], 'freight_agreements_org_carrier_status_idx')`
- `$table->index(['organization_id', 'valid_from', 'valid_to'], 'freight_agreements_org_validity_idx')`

### freight_rate_lines

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| rate_table_id | `foreignId` | constrained('freight_rate_tables'), cascadeOnDelete |
| origin_zone | `string(100)` | nullable — null = any |
| destination_zone | `string(100)` | nullable |
| weight_from | `decimal(10, 3)` | default(0) |
| weight_to | `decimal(10, 3)` | nullable — null = unlimited |
| volume_from | `decimal(10, 4)` | default(0) |
| volume_to | `decimal(10, 4)` | nullable |
| base_rate | `decimal(14, 4)` | default(0) — fixed charge |
| per_unit_rate | `decimal(14, 6)` | default(0) — per kg, per cbm, etc. |
| min_charge | `decimal(14, 4)` | default(0) |
| max_charge | `decimal(14, 4)` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['rate_table_id', 'origin_zone', 'destination_zone'], 'freight_rate_lines_table_zones_idx')`

### freight_surcharges

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| rate_table_id | `foreignId` | nullable, constrained('freight_rate_tables'), nullOnDelete |
| carrier_id | `foreignId` | nullable, constrained('carriers'), nullOnDelete |
| code | `string(30)` |  |
| name | `string(200)` |  |
| type | `string(30)` | default('fuel') |
| calculation_method | `string(20)` | default('pct') |
| value | `decimal(14, 6)` |  |
| currency_code | `string(5)` | default('USD') |
| valid_from | `date` |  |
| valid_to | `date` | nullable |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'type', 'is_active'], 'freight_surcharges_org_type_active_idx')`
- `$table->index(['organization_id', 'carrier_id', 'is_active'], 'freight_surcharges_org_carrier_active_idx')`

### freight_tender_requests

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| tender_number | `string(50)` |  |
| title | `string(200)` |  |
| origin_country | `string(5)` | nullable |
| origin_zone | `string(100)` | nullable |
| destination_country | `string(5)` | nullable |
| destination_zone | `string(100)` | nullable |
| transport_mode | `string(30)` | default('road') |
| total_weight | `decimal(14, 3)` | default(0) |
| total_volume | `decimal(14, 4)` | default(0) |
| shipment_count | `unsignedInteger` | default(1) |
| has_dangerous_goods | `boolean` | default(false) |
| requires_refrigeration | `boolean` | default(false) |
| required_by_date | `date` | nullable |
| bid_deadline | `dateTime` | nullable |
| status | `string(20)` | default('draft') |
| awarded_carrier_id | `foreignId` | nullable, constrained('carriers'), nullOnDelete |
| awarded_bid_id | `foreignId` | nullable — set after award |
| awarded_at | `dateTime` | nullable |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'tender_number'], 'freight_tender_requests_org_num_uniq')`
- `$table->index(['organization_id', 'status', 'bid_deadline'], 'freight_tender_requests_org_status_deadline_idx')`

### freight_tender_bids

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| tender_request_id | `foreignId` | constrained('freight_tender_requests'), cascadeOnDelete |
| carrier_id | `foreignId` | constrained('carriers'), cascadeOnDelete |
| total_price | `decimal(14, 4)` |  |
| currency_code | `string(5)` | default('USD') |
| transit_days | `unsignedSmallInteger` |  |
| valid_until | `date` | nullable |
| status | `string(20)` | default('submitted') |
| submitted_at | `dateTime` |  |
| evaluated_at | `dateTime` | nullable |
| notes | `text` | nullable |
| breakdown | `json` | nullable — itemized cost breakdown |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['tender_request_id', 'carrier_id'], 'freight_tender_bids_request_carrier_uniq')`
- `$table->index(['tender_request_id', 'status'], 'freight_tender_bids_request_status_idx')`

### freight_tender_items

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| tender_request_id | `foreignId` | constrained('freight_tender_requests'), cascadeOnDelete |
| description | `string(200)` |  |
| weight | `decimal(14, 3)` | default(0) |
| volume | `decimal(14, 4)` | default(0) |
| quantity | `decimal(12, 3)` | default(1) |
| unit_of_measure | `string(20)` | default('pcs') |
| cargo_type | `string(50)` | nullable — general\|dg\|refrigerated\|hazmat\|bulk |
| is_dangerous_goods | `boolean` | default(false) |
| un_number | `string(10)` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

### load_plans

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| plan_number | `string(50)` |  |
| status | `string(20)` | default('open') |
| carrier_id | `foreignId` | nullable, constrained('carriers'), nullOnDelete |
| carrier_service_id | `foreignId` | nullable, constrained('carrier_services'), nullOnDelete |
| vehicle_type | `string(50)` | nullable — truck\|van\|container_20ft\|container_40ft\|etc |
| vehicle_plate | `string(30)` | nullable |
| driver_name | `string(100)` | nullable |
| driver_contact | `string(50)` | nullable |
| max_weight | `decimal(14, 3)` | nullable — kg |
| max_volume | `decimal(14, 4)` | nullable — cbm |
| current_weight | `decimal(14, 3)` | default(0) |
| current_volume | `decimal(14, 4)` | default(0) |
| utilization_weight_pct | `decimal(5, 2)` | default(0) — computed |
| utilization_volume_pct | `decimal(5, 2)` | default(0) — computed |
| planned_departure | `dateTime` | nullable |
| actual_departure | `dateTime` | nullable |
| origin_location | `string(200)` | nullable |
| notes | `text` | nullable |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'plan_number'], 'load_plans_org_num_uniq')`
- `$table->index(['organization_id', 'status', 'planned_departure'], 'load_plans_org_status_departure_idx')`
- `$table->index(['organization_id', 'carrier_id', 'status'], 'load_plans_org_carrier_status_idx')`

### transportation_orders

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| order_number | `string(50)` |  |
| type | `string(20)` | default('outbound') — outbound\|inbound\|internal |
| status | `string(20)` | default('draft') |
| carrier_id | `foreignId` | nullable, constrained('carriers'), nullOnDelete |
| carrier_service_id | `foreignId` | nullable, constrained('carrier_services'), nullOnDelete |
| load_plan_id | `foreignId` | nullable, constrained('load_plans'), nullOnDelete |
| tender_request_id | `foreignId` | nullable, constrained('freight_tender_requests'), nullOnDelete |
| origin_address | `string(500)` | nullable |
| origin_country | `string(5)` | nullable |
| destination_address | `string(500)` | nullable |
| destination_country | `string(5)` | nullable |
| planned_departure | `dateTime` | nullable |
| planned_arrival | `dateTime` | nullable |
| actual_departure | `dateTime` | nullable |
| actual_arrival | `dateTime` | nullable |
| total_weight | `decimal(14, 3)` | default(0) |
| total_volume | `decimal(14, 4)` | default(0) |
| freight_cost | `decimal(14, 4)` | default(0) |
| currency_code | `string(5)` | default('USD') |
| tracking_number | `string(100)` | nullable |
| has_dangerous_goods | `boolean` | default(false) |
| created_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| notes | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'order_number'], 'transportation_orders_org_num_uniq')`
- `$table->index(['organization_id', 'status', 'planned_departure'], 'transportation_orders_org_status_departure_idx')`
- `$table->index(['organization_id', 'carrier_id', 'status'], 'transportation_orders_org_carrier_status_idx')`
- `$table->index(['organization_id', 'load_plan_id'], 'transportation_orders_org_load_plan_idx')`

### load_plan_items

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| load_plan_id | `foreignId` | constrained('load_plans'), cascadeOnDelete |
| transportation_order_id | `foreignId` | constrained('transportation_orders'), cascadeOnDelete |
| loading_sequence | `unsignedSmallInteger` | default(0) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['load_plan_id', 'transportation_order_id'], 'load_plan_items_plan_order_uniq')`

### transportation_order_items

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| transportation_order_id | `foreignId` | constrained('transportation_orders'), cascadeOnDelete |
| reference_type | `string(30)` | nullable |
| reference_id | `unsignedBigInteger` | nullable |
| reference_number | `string(50)` | nullable |
| product_id | `unsignedBigInteger` | nullable |
| description | `string(200)` |  |
| quantity | `decimal(14, 3)` | default(1) |
| unit_of_measure | `string(20)` | default('pcs') |
| weight | `decimal(14, 3)` | default(0) |
| volume | `decimal(14, 4)` | default(0) |
| is_dangerous_goods | `boolean` | default(false) |
| un_number | `string(10)` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['transportation_order_id'], 'transportation_order_items_order_idx')`
- `$table->index(['reference_type', 'reference_id'], 'transportation_order_items_ref_idx')`

## 0440_shared.php

### audit_log_archives

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | nullable, constrained('organizations'), nullOnDelete |
| event | `string(50)` |  |
| auditable_type | `string(255)` |  |
| auditable_id | `unsignedBigInteger` |  |
| user_id | `foreignId` | nullable, constrained('users'), nullOnDelete |
| old_values | `json` | nullable |
| new_values | `json` | nullable |
| ip_address | `string(45)` | nullable |
| archived_at | `timestamp` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'created_at'])`
- `$table->index('archived_at')`

### cache

| Column | Type | Details |
|---|---|---|
| key | `string` | primary |
| value | `mediumText` |  |
| expiration | `integer` | index |

### cache_locks

| Column | Type | Details |
|---|---|---|
| key | `string` | primary |
| owner | `string` |  |
| expiration | `integer` | index |

### calibration_equipment

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| equipment_code | `string(30)` |  |
| name | `string` |  |
| manufacturer | `string(100)` | nullable |
| model_number | `string(50)` | nullable |
| serial_number | `string(50)` | nullable |
| category | `string(50)` | nullable, comment('thermometer/pressure_gauge/scale/caliper/multimeter/other') |
| location | `string(100)` | nullable |
| responsible_person_id | `foreignId` | nullable, constrained('users'), nullOnDelete |
| purchase_date | `date` | nullable |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'equipment_code'], 'cal_equip_org_code_idx')`
- `$table->index(['organization_id', 'is_active'], 'cal_equip_org_active_idx')`

### capacity_slots

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations') |
| work_center_id | `foreignId` | constrained('work_centers', 'id', 'cap_slot_wc_fk') |
| slot_date | `date` |  |
| slot_start | `time` |  |
| slot_end | `time` |  |
| available_minutes | `unsignedSmallInteger` |  |
| allocated_minutes | `unsignedSmallInteger` | default(0) |
| utilization_pct | `decimal(5, 2)` | storedAs('CASE WHEN available_minutes = 0 THEN 0 ELSE ROUND(allocated_minutes / available_minutes * 100, 2) END') |
| is_available | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

### competency_frameworks

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| name | `string` |  |
| description | `text` | nullable |
| applicable_to | `enum(['all', 'department', 'designation', 'position'])` | default('all') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade')`

### competency_framework_skills

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| competency_framework_id | `unsignedBigInteger` |  |
| skill_id | `unsignedBigInteger` |  |
| required_level | `unsignedTinyInteger` |  |
| weight | `decimal(5, 2)` | default(1.00) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('competency_framework_id', 'cf_skill_cf_fk')->references('id')->on('competency_frameworks')->onDelete('cascade')`
- `$table->foreign('skill_id', 'cf_skill_skill_fk')->references('id')->on('skills')->onDelete('cascade')`

### conversation_participants

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| conversation_id | `foreignId` | constrained, cascadeOnDelete |
| user_id | `foreignId` | constrained, cascadeOnDelete |
| last_read_at | `timestamp` | nullable |
| joined_at | `timestamp` | useCurrent |
| left_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['conversation_id', 'user_id'])`

### daily_work_schedules

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| name | `string` |  |
| work_start | `time` |  |
| work_end | `time` |  |
| break_start | `time` | nullable |
| break_end | `time` | nullable |
| planned_hours | `decimal(4, 2)` |  |
| day_type | `enum(['normal', 'reduced', 'off', 'public_holiday'])` | default('normal') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade')`

### failed_jobs

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `string` | unique |
| connection | `text` |  |
| queue | `text` |  |
| payload | `longText` |  |
| exception | `longText` |  |
| failed_at | `timestamp` | useCurrent |

### financial_close_task_dependencies

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| financial_close_task_id | `unsignedBigInteger` |  |
| depends_on_task_id | `unsignedBigInteger` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['financial_close_task_id', 'depends_on_task_id'], 'uq_fctdep_task_dep')`

Foreign keys:

- `$table->foreign('financial_close_task_id', 'fk_fctdep_task')->references('id')->on('financial_close_tasks')->onDelete('cascade')`
- `$table->foreign('depends_on_task_id', 'fk_fctdep_depends')->references('id')->on('financial_close_tasks')->onDelete('cascade')`

### financial_idempotency_keys

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| key | `string(255)` |  |
| operation | `string(100)` |  |
| organization_id | `unsignedBigInteger` |  |
| status | `enum(['processing', 'completed', 'failed'])` | default('processing') |
| request_hash | `string(64)` | nullable |
| response_payload | `json` | nullable |
| expires_at | `timestamp` | index |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['key', 'organization_id', 'operation'], 'fin_idempotency_scope_unique')`
- `$table->index('organization_id')`

### location_equipment

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| floc_id | `unsignedBigInteger` |  |
| equipment_number | `string` |  |
| description | `string` |  |
| category | `string` | nullable |
| manufacturer | `string` | nullable |
| model | `string` | nullable |
| serial_number | `string` | nullable |
| installed_at | `date` | nullable |
| removed_at | `date` | nullable |
| product_id | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete()`
- `$table->foreign('floc_id', 'floc_eq_floc_fk')->references('id')->on('functional_locations')->cascadeOnDelete()`
- `$table->foreign('product_id', 'floc_eq_prod_fk')->references('id')->on('products')->nullOnDelete()`

### hr_budget_plans

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| fiscal_year | `unsignedSmallInteger` |  |
| department_id | `unsignedBigInteger` | nullable |
| plan_name | `string` |  |
| status | `enum(['draft', 'submitted', 'approved'])` | default('draft') |
| approved_by | `unsignedBigInteger` | nullable |
| approved_at | `timestamp` | nullable |
| total_headcount | `unsignedInteger` | default(0) |
| total_salary_budget | `decimal(18, 4)` | default(0) |
| total_benefits_budget | `decimal(18, 4)` | default(0) |
| currency | `char(3)` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade')`
- `$table->foreign('department_id', 'hr_budget_dept_fk')->references('id')->on('departments')->onDelete('set null')`
- `$table->foreign('approved_by', 'hr_budget_appr_fk')->references('id')->on('users')->onDelete('set null')`

### idempotency_keys

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| key | `string(64)` | unique |
| user_id | `foreignId` | constrained, cascadeOnDelete |
| endpoint | `string` |  |
| response | `text` | nullable |
| status_code | `smallInteger` |  |
| created_at | `timestamp` | useCurrent |
| expires_at | `timestamp` |  |

Indexes:

- `$table->index('expires_at')`

### invoice_archives

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| invoice_number | `string(50)` | nullable |
| status | `string(30)` | default('paid') |
| invoice_date | `date` |  |
| due_date | `date` | nullable |
| subtotal | `decimal(20, 4)` | default(0) |
| tax_amount | `decimal(20, 4)` | default(0) |
| total | `decimal(20, 4)` | default(0) |
| amount_paid | `decimal(20, 4)` | default(0) |
| amount_due | `decimal(20, 4)` | default(0) |
| currency_code | `string(10)` | default('SAR') |
| snapshot | `json` | nullable |
| archived_at | `timestamp` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'invoice_date'])`
- `$table->index('archived_at')`

### job_batches

| Column | Type | Details |
|---|---|---|
| id | `string` | primary |
| name | `string` |  |
| total_jobs | `integer` |  |
| pending_jobs | `integer` |  |
| failed_jobs | `integer` |  |
| failed_job_ids | `longText` |  |
| options | `mediumText` | nullable |
| cancelled_at | `integer` | nullable |
| created_at | `integer` |  |
| finished_at | `integer` | nullable |

### jobs

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| queue | `string` | index |
| payload | `longText` |  |
| attempts | `unsignedTinyInteger` |  |
| reserved_at | `unsignedInteger` | nullable |
| available_at | `unsignedInteger` |  |
| created_at | `unsignedInteger` |  |

### journal_entry_archives

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| entry_number | `string(50)` | nullable |
| type | `string(50)` | nullable |
| reference | `string(255)` | nullable |
| description | `string` | nullable |
| entry_date | `date` |  |
| total_debit | `decimal(20, 4)` | default(0) |
| total_credit | `decimal(20, 4)` | default(0) |
| status | `string(20)` | default('posted') |
| currency_code | `string(10)` | default('SAR') |
| metadata | `json` | nullable |
| archived_at | `timestamp` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'entry_date'])`
- `$table->index('archived_at')`

## 0450_shared_2.php

### login_attempts

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| email | `string` |  |
| ip_address | `string(45)` |  |
| successful | `boolean` | default(false) |
| attempted_at | `timestamp` | useCurrent |

Indexes:

- `$table->index(['email', 'attempted_at'])`
- `$table->index(['ip_address', 'attempted_at'])`

### org_positions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| position_title | `string` |  |
| org_unit_id | `unsignedBigInteger` |  |
| designation_id | `unsignedBigInteger` | nullable |
| reports_to_position_id | `unsignedBigInteger` | nullable |
| headcount_budget | `unsignedSmallInteger` | default(1) |
| current_headcount | `unsignedSmallInteger` | default(0) |
| is_key_position | `boolean` | default(false) |
| valid_from | `date` |  |
| valid_to | `date` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade')`
- `$table->foreign('org_unit_id', 'org_pos_unit_fk')->references('id')->on('org_units')->onDelete('cascade')`
- `$table->foreign('designation_id', 'org_pos_desig_fk')->references('id')->on('designations')->onDelete('set null')`
- `$table->foreign('reports_to_position_id', 'org_pos_rpt_fk')->references('id')->on('org_positions')->onDelete('set null')`

### password_changes

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| user_id | `foreignId` | constrained, cascadeOnDelete |
| changed_at | `timestamp` | useCurrent |
| ip_address | `string(45)` | nullable |

Indexes:

- `$table->index(['user_id', 'changed_at'])`

### password_reset_tokens

| Column | Type | Details |
|---|---|---|
| email | `string` | primary |
| token | `string` |  |
| created_at | `timestamp` | nullable |

### payroll_schemas

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| schema_name | `string` |  |
| description | `text` | nullable |
| country_code | `char(2)` |  |
| active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade')`

### period_work_schedules

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| name | `string` |  |
| description | `text` | nullable |
| cycle_length_weeks | `unsignedTinyInteger` | default(1) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade')`

### production_confirmations

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations') |
| confirmation_number | `string` | unique |
| work_order_id | `foreignId` | constrained('work_orders', 'id', 'prod_conf_wo_fk') |
| operation_id | `foreignId` | nullable, constrained('work_order_operations', 'id', 'prod_conf_op_fk') |
| confirmation_type | `enum(['partial', 'final', 'milestone'])` |  |
| confirmed_quantity | `decimal(18, 4)` |  |
| scrap_quantity | `decimal(18, 4)` | default(0) |
| rework_quantity | `decimal(18, 4)` | default(0) |
| actual_setup_time | `decimal(8, 2)` | nullable |
| actual_machine_time | `decimal(8, 2)` | nullable |
| actual_labor_time | `decimal(8, 2)` | nullable |
| time_uom | `enum(['minutes', 'hours'])` | default('minutes') |
| confirmed_by | `foreignId` | constrained('users', 'id', 'prod_conf_usr_fk') |
| confirmed_at | `timestamp` |  |
| posting_date | `date` |  |
| shift | `enum(['morning', 'afternoon', 'night'])` | nullable |
| is_final | `boolean` | default(false) |
| reversal_id | `foreignId` | nullable, constrained('production_confirmations', 'id', 'prod_conf_rev_fk') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

### promotion_customers

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| promotion_id | `foreignId` | constrained, cascadeOnDelete |
| contact_id | `foreignId` | nullable, constrained, cascadeOnDelete |
| customer_group_id | `foreignId` | nullable, constrained, cascadeOnDelete |
| is_excluded | `boolean` | default(false) |

Indexes:

- `$table->index(['promotion_id', 'contact_id'])`
- `$table->index(['promotion_id', 'customer_group_id'])`

### promotion_products

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| promotion_id | `foreignId` | constrained, cascadeOnDelete |
| product_id | `foreignId` | nullable, constrained, cascadeOnDelete |
| category_id | `foreignId` | nullable, constrained('categories'), cascadeOnDelete |
| is_excluded | `boolean` | default(false) — true = exclude this product/category |

Indexes:

- `$table->index(['promotion_id', 'product_id'])`
- `$table->index(['promotion_id', 'category_id'])`

### report_definitions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| organization_id | `foreignId` | nullable, constrained, cascadeOnDelete — NULL for system reports |
| code | `string(50)` | unique |
| name | `string` |  |
| description | `text` | nullable |
| module | `string(30)` | sales, purchase, inventory, accounting, hr, etc. |
| category | `string(30)` | financial, operational, analytical, compliance |
| report_type | `string(30)` | list, summary, chart, pivot, combined |
| columns | `json` | nullable — Available columns |
| filters | `json` | nullable — Available filters |
| groupings | `json` | nullable — Available groupings |
| aggregations | `json` | nullable — Sum, avg, count, etc. |
| default_config | `json` | nullable — Default settings |
| available_formats | `json` | ['pdf', 'xlsx', 'csv', 'html'] |
| default_format | `string(10)` | default('pdf') |
| required_permission | `string` | nullable |
| is_system | `boolean` | default(false) |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['module', 'is_active'])`

### role_permissions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| role_id | `foreignId` | constrained, cascadeOnDelete |
| permission_id | `foreignId` | constrained, cascadeOnDelete |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['role_id', 'permission_id'])`

### scheduling_runs

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations') |
| run_number | `string` | unique |
| scheduling_type | `enum(['forward', 'backward', 'finite', 'infinite'])` |  |
| horizon_start | `date` |  |
| horizon_end | `date` |  |
| work_center_ids | `json` | nullable |
| status | `enum(['pending', 'running', 'completed', 'failed'])` | default('pending') |
| started_at | `timestamp` | nullable |
| completed_at | `timestamp` | nullable |
| run_by | `foreignId` | constrained('users', 'id', 'sched_run_usr_fk') |
| summary | `json` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

### sessions

| Column | Type | Details |
|---|---|---|
| id | `string` | primary |
| user_id | `foreignId` | nullable, index |
| ip_address | `string(45)` | nullable |
| user_agent | `text` | nullable |
| payload | `longText` |  |
| last_activity | `integer` | index |

### shop_floor_papers

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations') |
| paper_number | `string` | unique |
| work_order_id | `foreignId` | constrained('work_orders', 'id', 'sfp_wo_fk') |
| paper_type | `enum(['operation_sheet', 'component_list', 'routing_sheet', 'traveler', 'label', ])` |  |
| printed_at | `timestamp` | nullable |
| printed_by | `foreignId` | nullable, constrained('users', 'id', 'sfp_usr_fk') |
| reprint_count | `unsignedSmallInteger` | default(0) |
| paper_data | `json` |  |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

### staging_requests

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations') |
| request_number | `string` | unique |
| work_order_id | `foreignId` | nullable, constrained('work_orders', 'id', 'stag_req_wo_fk') |
| production_supply_area | `string` | nullable |
| requested_by | `foreignId` | constrained('users', 'id', 'stag_req_usr_fk') |
| required_date | `date` |  |
| status | `enum(['open', 'in_progress', 'staged', 'cancelled'])` | default('open') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

### staging_request_lines

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| staging_request_id | `foreignId` | constrained('staging_requests', 'id', 'stag_line_req_fk') |
| product_id | `foreignId` | constrained('products', 'id', 'stag_line_prod_fk') |
| required_quantity | `decimal(18, 4)` |  |
| staged_quantity | `decimal(18, 4)` | default(0) |
| uom | `string(20)` |  |
| source_warehouse_id | `foreignId` | nullable, constrained('warehouses', 'id', 'stag_line_wh_fk') |
| source_location_id | `foreignId` | nullable, constrained('warehouse_locations', 'id', 'stag_line_loc_fk') |
| status | `enum(['open', 'partial', 'complete'])` | default('open') |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

### technical_completion_records

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations') |
| work_order_id | `foreignId` | unique, constrained('work_orders', 'id', 'teco_wo_fk') |
| teco_date | `date` |  |
| teco_by | `foreignId` | constrained('users', 'id', 'teco_usr_fk') |
| remaining_quantity | `decimal(18, 4)` | nullable |
| settlement_status | `enum(['pending', 'settled', 'cancelled'])` | default('pending') |
| settlement_date | `date` | nullable |
| reason | `text` | nullable |
| reversed_by | `foreignId` | nullable, constrained('users', 'id', 'teco_rev_usr_fk') |
| reversed_at | `timestamp` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

### token_blacklist

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| jti | `string(64)` | unique — JWT ID |
| user_id | `foreignId` | nullable, constrained, cascadeOnDelete |
| reason | `string(50)` | logout, password_change, revoked, user_deleted |
| expires_at | `timestamp` | When token would naturally expire (for cleanup) |
| created_at | `timestamp` | useCurrent |

Indexes:

- `$table->index('expires_at')`

### user_branches

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| user_id | `foreignId` | constrained, cascadeOnDelete |
| branch_id | `foreignId` | constrained, cascadeOnDelete |
| is_default | `boolean` | default(false) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['user_id', 'branch_id'])`

### user_roles

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| user_id | `foreignId` | constrained, cascadeOnDelete |
| role_id | `foreignId` | constrained, cascadeOnDelete |
| branch_id | `foreignId` | nullable, constrained, cascadeOnDelete — null = all branches |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['user_id', 'role_id', 'branch_id'])`

### user_segment_memberships

| Column | Type | Details |
|---|---|---|
| user_id | `foreignId` | constrained, cascadeOnDelete |
| segment_id | `foreignId` | references('id'), on('user_segments'), cascadeOnDelete |
| assigned_at | `timestamp` | useCurrent |

Indexes:

- `$table->primary(['user_id', 'segment_id'])`

### wage_type_catalog

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| wage_type_code | `string(4)` |  |
| description | `string` |  |
| category | `enum(['earnings', 'deductions', 'employer_contributions', 'informational'])` |  |
| processing_class | `string(2)` | nullable |
| evaluation_class | `string(2)` | nullable |
| cumulation_class | `string(2)` | nullable |
| taxable | `boolean` | default(true) |
| pensionable | `boolean` | default(true) |
| active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->unique(['organization_id', 'wage_type_code'], 'wage_type_org_code_unique')`

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade')`

## 0460_shared_3.php

### work_schedule_rules

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| name | `string` |  |
| period_work_schedule_id | `unsignedBigInteger` |  |
| reference_date | `date` |  |
| daily_hours | `decimal(4, 2)` |  |
| weekly_hours | `decimal(5, 2)` |  |
| monthly_hours | `decimal(6, 2)` |  |
| overtime_threshold_daily | `decimal(4, 2)` | nullable |
| overtime_threshold_weekly | `decimal(5, 2)` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade')`
- `$table->foreign('period_work_schedule_id', 'wsr_pws_fk')->references('id')->on('period_work_schedules')->onDelete('restrict')`

### equipment_counters

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| counter_name | `string` |  |
| equipment_id | `unsignedBigInteger` | nullable |
| floc_id | `unsignedBigInteger` | nullable |
| uom | `string(20)` |  |
| current_reading | `decimal(14, 3)` | default(0) |
| overflow_value | `decimal(14, 3)` | nullable |
| active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete()`
- `$table->foreign('equipment_id', 'pm_ctr_eq_fk')->references('id')->on('location_equipment')->nullOnDelete()`
- `$table->foreign('floc_id', 'pm_ctr_floc_fk')->references('id')->on('functional_locations')->nullOnDelete()`

### counter_based_plans

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| plan_number | `string` | unique |
| plan_type | `enum(['time_based', 'counter_based', 'condition_based'])` |  |
| floc_id | `unsignedBigInteger` | nullable |
| counter_id | `unsignedBigInteger` | nullable |
| task_list_id | `unsignedBigInteger` | nullable |
| counter_interval | `decimal(14, 3)` | nullable |
| threshold_warning | `decimal(14, 3)` | nullable |
| last_maintenance_reading | `decimal(14, 3)` | nullable |
| next_due_reading | `decimal(14, 3)` | nullable |
| active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete()`
- `$table->foreign('floc_id', 'pm_plan_floc_fk')->references('id')->on('functional_locations')->nullOnDelete()`
- `$table->foreign('counter_id', 'pm_plan_ctr_fk')->references('id')->on('equipment_counters')->nullOnDelete()`
- `$table->foreign('task_list_id', 'pm_plan_tl_fk')->references('id')->on('maintenance_task_lists')->nullOnDelete()`

### counter_based_orders

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| order_number | `string` | unique |
| maintenance_plan_id | `unsignedBigInteger` | nullable |
| floc_id | `unsignedBigInteger` | nullable |
| order_type | `enum(['preventive', 'corrective', 'breakdown', 'inspection'])` |  |
| description | `text` |  |
| status | `enum(['created', 'released', 'in_progress', 'completed', 'closed', 'cancelled'])` | default('created') |
| priority | `enum(['urgent', 'high', 'normal', 'low'])` | default('normal') |
| planned_start | `date` | nullable |
| planned_end | `date` | nullable |
| actual_start | `date` | nullable |
| actual_end | `date` | nullable |
| counter_reading_at_trigger | `decimal(14, 3)` | nullable |
| assigned_to | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete()`
- `$table->foreign('maintenance_plan_id', 'pm_order_plan_fk')->references('id')->on('counter_based_plans')->nullOnDelete()`
- `$table->foreign('floc_id', 'pm_order_floc_fk')->references('id')->on('functional_locations')->nullOnDelete()`
- `$table->foreign('assigned_to', 'pm_order_usr_fk')->references('id')->on('users')->nullOnDelete()`

### counter_readings

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `unsignedBigInteger` |  |
| counter_id | `unsignedBigInteger` |  |
| reading_value | `decimal(14, 3)` |  |
| reading_date | `dateTime` |  |
| delta_value | `decimal(14, 3)` | nullable |
| recorded_by | `unsignedBigInteger` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Foreign keys:

- `$table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete()`
- `$table->foreign('counter_id', 'pm_ctr_read_ctr_fk')->references('id')->on('equipment_counters')->cascadeOnDelete()`
- `$table->foreign('recorded_by', 'pm_ctr_read_usr_fk')->references('id')->on('users')->nullOnDelete()`

### calibration_plans

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| calibration_equipment_id | `foreignId` | constrained('calibration_equipment'), cascadeOnDelete |
| plan_code | `string(30)` |  |
| calibration_interval_days | `integer` |  |
| tolerance_low | `decimal(10, 4)` | nullable |
| tolerance_high | `decimal(10, 4)` | nullable |
| measurement_unit | `string(20)` | nullable |
| calibration_procedure | `text` | nullable |
| external_lab | `string(100)` | nullable |
| is_active | `boolean` | default(true) |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['calibration_equipment_id', 'is_active'], 'cal_plan_equip_active_idx')`
- `$table->index(['organization_id', 'plan_code'], 'cal_plan_org_code_idx')`

### calibration_orders

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| calibration_equipment_id | `foreignId` | constrained('calibration_equipment'), cascadeOnDelete |
| calibration_plan_id | `foreignId` | nullable, constrained('calibration_plans'), nullOnDelete |
| order_number | `string(30)` |  |
| scheduled_date | `date` |  |
| completed_date | `date` | nullable |
| status | `string(20)` | default('planned'), comment('planned/in_progress/completed/overdue/cancelled') |
| calibrated_by | `foreignId` | nullable, constrained('users'), nullOnDelete |
| external_lab | `string(100)` | nullable |
| result | `string(20)` | nullable, comment('pass/fail/conditional') |
| actual_measurement | `decimal(12, 4)` | nullable |
| notes | `text` | nullable |
| next_calibration_date | `date` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |
| deleted_at | `timestamp` | nullable |

Indexes:

- `$table->index(['calibration_equipment_id', 'status'], 'cal_order_equip_status_idx')`
- `$table->index(['scheduled_date', 'status'], 'cal_order_date_status_idx')`
- `$table->index('calibration_plan_id', 'cal_order_plan_idx')`

### calibration_certificates

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained('organizations'), cascadeOnDelete |
| calibration_order_id | `foreignId` | constrained('calibration_orders'), cascadeOnDelete |
| certificate_number | `string(50)` |  |
| issued_date | `date` |  |
| valid_until | `date` |  |
| issued_by | `string(100)` | nullable |
| accreditation_body | `string(100)` | nullable |
| certificate_data | `json` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index('calibration_order_id', 'cal_cert_order_idx')`
- `$table->index(['organization_id', 'certificate_number'], 'cal_cert_org_num_idx')`

### saved_reports

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| user_id | `foreignId` | constrained, cascadeOnDelete |
| report_type | `string(50)` | One of ReportDataService::TYPES |
| name | `string` |  |
| description | `text` | nullable |
| parameters | `json` | nullable — Date range, warehouse and the like |
| columns | `json` | nullable |
| export_format | `string(10)` | default('pdf') |
| is_scheduled | `boolean` | default(false) |
| schedule_frequency | `string(20)` | nullable — daily, weekly, monthly, quarterly |
| schedule_day | `string(10)` | nullable — Weekday name when weekly, day of month when monthly |
| schedule_time | `time` | nullable |
| recipients | `json` | nullable — Email addresses |
| last_run_at | `timestamp` | nullable |
| next_run_at | `timestamp` | nullable |
| is_shared | `boolean` | default(false) — Shared with organization |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'user_id'])`
- `$table->index(['is_scheduled', 'next_run_at'])`

### report_executions

| Column | Type | Details |
|---|---|---|
| id | `id` |  |
| uuid | `uuid` | unique |
| organization_id | `foreignId` | constrained, cascadeOnDelete |
| saved_report_id | `foreignId` | nullable, constrained, nullOnDelete |
| report_type | `string(50)` |  |
| user_id | `foreignId` | nullable, constrained, nullOnDelete |
| parameters | `json` | nullable — Filters, date range, etc. |
| format | `string(10)` |  |
| trigger | `string(20)` | manual, scheduled, api |
| status | `string(20)` | default('pending') — pending, running, completed, failed |
| started_at | `timestamp` | nullable |
| completed_at | `timestamp` | nullable |
| execution_time_ms | `unsignedInteger` | nullable |
| row_count | `unsignedInteger` | nullable |
| file_path | `string` | nullable |
| file_name | `string` | nullable |
| file_size | `unsignedBigInteger` | nullable |
| expires_at | `timestamp` | nullable |
| error_message | `text` | nullable |
| created_at | `timestamp` | nullable |
| updated_at | `timestamp` | nullable |

Indexes:

- `$table->index(['organization_id', 'created_at'])`
- `$table->index(['status', 'created_at'])`

## 0470_deferred_keys.php

Changes tables created earlier: `leads`, `sales_returns`, `leave_balances`.

## 0480_mysql_indexes.php

Changes tables created earlier: `product_attribute_values`, `failed_jobs_monitor`.

## 0490_goods_receipt_inspection_status.php

Changes tables created earlier: `goods_receipts`.

## 0500_stock_movement_material_types.php

Changes tables created earlier: `stock_movements`.

## 0510_recurring_profile_log_created_nullable.php

Changes tables created earlier: `recurring_profile_logs`.

## 0520_continue_number_sequences_after_stored_numbers.php

Changes no table structure; it only reads or writes data.

## 0530_purchase_order_pending_approval_status.php

Changes tables created earlier: `purchase_orders`.

## 0540_messaging_channel_default_marker.php

Changes tables created earlier: `messaging_channels`.

## 0550_organization_parent.php

Changes tables created earlier: `organizations`.
