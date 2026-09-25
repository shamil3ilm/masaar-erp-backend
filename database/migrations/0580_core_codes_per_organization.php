<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Makes the core identifiers unique within an organization.
 *
 * A business partner number, a classification key and an email template
 * code were unique across every organization.
 *
 * Two organizations numbering their own documents reach the same value, and
 * the second was refused - which also told it the value exists somewhere it
 * cannot see. The index now carries organization_id, so the value is theirs
 * alone within their organization and free everywhere else.
 *
 * The classification keys already held to one organization through the
 * class they hang off; naming the column says so. The template code takes
 * the language with it, because EmailTemplate::getTemplate() reads a
 * template by organization, code and language, and one row per code left
 * no room for a second language or for an organization to override the
 * system template.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_partners', function (Blueprint $table) {
            $table->dropUnique('business_partners_bp_number_unique');
            $table->unique(['organization_id', 'bp_number'], 'business_partners_org_bp_number_unq');
        });

        Schema::table('class_assignments', function (Blueprint $table) {
            $table->dropUnique('ca_class_obj_unq');
            $table->unique(['organization_id', 'classification_class_id', 'object_type', 'object_id'], 'ca_org_class_obj_unq');
        });

        Schema::table('class_characteristic_values', function (Blueprint $table) {
            $table->dropUnique('ccv_char_obj_unq');
            $table->unique(['organization_id', 'class_characteristic_id', 'object_type', 'object_id'], 'ccv_org_char_obj_unq');
        });

        Schema::table('class_characteristics', function (Blueprint $table) {
            $table->dropUnique('cchar_class_code_unq');
            $table->unique(['organization_id', 'classification_class_id', 'characteristic_code'], 'cchar_org_class_code_unq');
        });

        Schema::table('email_templates', function (Blueprint $table) {
            $table->dropUnique('email_templates_code_unique');
            $table->unique(['organization_id', 'code', 'language'], 'email_templates_org_code_lang_unq');
        });

    }

    public function down(): void
    {
        Schema::table('business_partners', function (Blueprint $table) {
            $table->dropUnique('business_partners_org_bp_number_unq');
            $table->unique('bp_number', 'business_partners_bp_number_unique');
        });

        Schema::table('class_assignments', function (Blueprint $table) {
            $table->dropUnique('ca_org_class_obj_unq');
            $table->unique(['classification_class_id', 'object_type', 'object_id'], 'ca_class_obj_unq');
        });

        Schema::table('class_characteristic_values', function (Blueprint $table) {
            $table->dropUnique('ccv_org_char_obj_unq');
            $table->unique(['class_characteristic_id', 'object_type', 'object_id'], 'ccv_char_obj_unq');
        });

        Schema::table('class_characteristics', function (Blueprint $table) {
            $table->dropUnique('cchar_org_class_code_unq');
            $table->unique(['classification_class_id', 'characteristic_code'], 'cchar_class_code_unq');
        });

        Schema::table('email_templates', function (Blueprint $table) {
            $table->dropUnique('email_templates_org_code_lang_unq');
            $table->unique('code', 'email_templates_code_unique');
        });

    }
};
