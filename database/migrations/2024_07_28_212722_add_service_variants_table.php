public function up()
{
    Schema::create('service_variants', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('service_id'); // Must match services.id type
        $table->string('variant_name');
        $table->decimal('price', 10, 2);
        $table->json('features')->nullable();
        $table->timestamps();

        // Add explicit index
        $table->index('service_id');
        
        // Foreign key with proper references
        $table->foreign('service_id')
              ->references('id')
              ->on('services')
              ->onDelete('cascade');
    });
}