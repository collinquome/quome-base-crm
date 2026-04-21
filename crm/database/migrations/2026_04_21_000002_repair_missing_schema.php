<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Some environments have prior migrations marked as "Ran" in the migrations
     * table while the corresponding columns / tables are missing from the
     * database (e.g. from partial dumps or rebuilds). This migration
     * idempotently restores what those earlier migrations were supposed to
     * create, without disturbing environments where the schema is already
     * correct.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'image')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('image')->nullable();
            });
        }

        if (! Schema::hasTable('datagrid_saved_filters')) {
            Schema::create('datagrid_saved_filters', function (Blueprint $table) {
                $table->id();
                $table->integer('user_id')->unsigned();
                $table->string('name');
                $table->string('src');
                $table->json('applied');
                $table->timestamps();

                $table->unique(['user_id', 'name', 'src']);
            });
        }

        if (! Schema::hasTable('product_tags')) {
            Schema::create('product_tags', function (Blueprint $table) {
                $table->integer('tag_id')->unsigned();
                $table->foreign('tag_id')->references('id')->on('tags')->onDelete('cascade');

                $table->integer('product_id')->unsigned();
                $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        // Repair-only — rolling back would risk dropping schema other code paths depend on.
    }
};
