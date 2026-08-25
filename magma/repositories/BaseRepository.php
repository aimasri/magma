<?php

/**
 * Title: BaseRepository (Deprecated)
 *
 * Purpose:
 * - Previously acted as the foundation for all repository classes.
 * - This file is deprecated and currently serves only as a tombstone.
 *
 * Why / Why this design:
 * - Transitioned to CQRS architecture.
 * - Segregating read (Query) and write (Command) repositories adheres strictly to the Interface Segregation Principle and Single Responsibility Principle.
 *
 * Teaching notes:
 * - Do not use this file or attempt to reinstate a monolithic base repository. Refer to BaseQueryRepository and BaseCommandRepository instead.
 */
// This file is deprecated. BaseRepository has been removed in favor of CQRS BaseQueryRepository and BaseCommandRepository.
